<?php
//namespace library;
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/8/30
 * Time: 17:41
 */
class Controller{
    /**
     * Enable XSS flag
     *
     * Determines whether the XSS filter is always active when
     * GET, POST or COOKIE data is encountered.
     * Set automatically based on config setting.
     *
     * @var    bool
     */
    protected $_enable_xss = FALSE;
    protected $_never_allowed_str =    array(
        'document.cookie'    => '[removed]',
        'document.write'    => '[removed]',
        '.parentNode'        => '[removed]',
        '.innerHTML'        => '[removed]',
        '-moz-binding'        => '[removed]',
        '<!--'                => '&lt;!--',
        '-->'                => '--&gt;',
        '<![CDATA['            => '&lt;![CDATA[',
        '<comment>'            => '&lt;comment&gt;'
    );
    protected $_never_allowed_regex = array(
        'javascript\s*:',
        '(document|(document\.)?window)\.(location|on\w*)',
        'expression\s*(\(|&\#40;)', // CSS and IE
        'vbscript\s*:', // IE, surprise!
        'wscript\s*:', // IE
        'jscript\s*:', // IE
        'vbs\s*:', // IE
        'Redirect\s+30\d',
        "([\"'])?data\s*:[^\\1]*?base64[^\\1]*?,[^\\1]*?\\1?"
    );
    /**
     * Do Never Allowed
     *
     * @used-by    CI_Security::xss_clean()
     * @param     string
     * @return     string
     */
    protected function _do_never_allowed($str)
    {
        $str = str_replace(array_keys($this->_never_allowed_str), $this->_never_allowed_str, $str);

        foreach ($this->_never_allowed_regex as $regex)
        {
            $str = preg_replace('#'.$regex.'#is', '[removed]', $str);
        }

        return $str;
    }
    /**
     * Fetch from array
     *
     * Internal method used to retrieve values from global arrays.
     *
     * @param    array    &$array        $_GET, $_POST, $_COOKIE, $_SERVER, etc.
     * @param    mixed    $index        Index for item to be fetched from $array
     * @param    bool    $xss_clean    Whether to apply XSS filtering
     * @return    mixed
     */
    protected function _fetch_from_array(&$array, $index = NULL, $xss_clean = NULL)
    {
        is_bool($xss_clean) OR $xss_clean = $this->_enable_xss;

        // If $index is NULL, it means that the whole $array is requested
        isset($index) OR $index = array_keys($array);

        // allow fetching multiple keys at once
        if (is_array($index))
        {
            $output = array();
            foreach ($index as $key)
            {
                $output[$key] = $this->_fetch_from_array($array, $key, $xss_clean);
            }

            return $output;
        }

        if (isset($array[$index]))
        {
            $value = $array[$index];
        }
        elseif (($count = preg_match_all('/(?:^[^\[]+)|\[[^]]*\]/', $index, $matches)) > 1) // Does the index contain array notation
        {
            $value = $array;
            for ($i = 0; $i < $count; $i++)
            {
                $key = trim($matches[0][$i], '[]');
                if ($key === '') // Empty notation will return the value as array
                {
                    break;
                }

                if (isset($value[$key]))
                {
                    $value = $value[$key];
                }
                else
                {
                    return NULL;
                }
            }
        }
        else
        {
            return NULL;
        }

        return ($xss_clean === TRUE)
            ? $this->xss_clean($value)
            : $value;
    }
    /**
     * XSS Clean
     *
     * Sanitizes data so that Cross Site Scripting Hacks can be
     * prevented.  This method does a fair amount of work but
     * it is extremely thorough, designed to prevent even the
     * most obscure XSS attempts.  Nothing is ever 100% foolproof,
     * of course, but I haven't been able to get anything passed
     * the filter.
     *
     * Note: Should only be used to deal with data upon submission.
     *     It's not something that should be used for general
     *     runtime processing.
     *
     * @link    http://channel.bitflux.ch/wiki/XSS_Prevention
     *         Based in part on some code and ideas from Bitflux.
     *
     * @link    http://ha.ckers.org/xss.html
     *         To help develop this script I used this great list of
     *        vulnerabilities along with a few other hacks I've
     *        harvested from examining vulnerabilities in other programs.
     *
     * @param    string|string[]    $str        Input data
     * @param     bool        $is_image    Whether the input is an image
     * @return    string
     */
    public function xss_clean($str, $is_image = FALSE)
    {
        // Is the string an array?
        if (is_array($str))
        {
            while (list($key) = each($str))
            {
                $str[$key] = $this->xss_clean($str[$key]);
            }

            return $str;
        }

        // Remove Invisible Characters
        $str = remove_invisible_characters($str);

        /*
         * URL Decode
         *
         * Just in case stuff like this is submitted:
         *
         * <a href="http://%77%77%77%2E%67%6F%6F%67%6C%65%2E%63%6F%6D">Google</a>
         *
         * Note: Use rawurldecode() so it does not remove plus signs
         */
        do
        {
            $str = rawurldecode($str);
        }
        while (preg_match('/%[0-9a-f]{2,}/i', $str));

        /*
         * Convert character entities to ASCII
         *
         * This permits our tests below to work reliably.
         * We only convert entities that are within tags since
         * these are the ones that will pose security problems.
         */
        $str = preg_replace_callback("/[^a-z0-9>]+[a-z0-9]+=([\'\"]).*?\\1/si", array($this, '_convert_attribute'), $str);
        $str = preg_replace_callback('/<\w+.*/si', array($this, '_decode_entity'), $str);

        // Remove Invisible Characters Again!
        $str = remove_invisible_characters($str);

        /*
         * Convert all tabs to spaces
         *
         * This prevents strings like this: ja    vascript
         * NOTE: we deal with spaces between characters later.
         * NOTE: preg_replace was found to be amazingly slow here on
         * large blocks of data, so we use str_replace.
         */
        $str = str_replace("\t", ' ', $str);

        // Capture converted string for later comparison
        $converted_string = $str;

        // Remove Strings that are never allowed
        $str = $this->_do_never_allowed($str);

        /*
         * Makes PHP tags safe
         *
         * Note: XML tags are inadvertently replaced too:
         *
         * <?xml
         *
         * But it doesn't seem to pose a problem.
         */
        if ($is_image === TRUE)
        {
            // Images have a tendency to have the PHP short opening and
            // closing tags every so often so we skip those and only
            // do the long opening tags.
            $str = preg_replace('/<\?(php)/i', '&lt;?\\1', $str);
        }
        else
        {
            $str = str_replace(array('<?', '?'.'>'), array('&lt;?', '?&gt;'), $str);
        }

        /*
         * Compact any exploded words
         *
         * This corrects words like:  j a v a s c r i p t
         * These words are compacted back to their correct state.
         */
        $words = array(
            'javascript', 'expression', 'vbscript', 'jscript', 'wscript',
            'vbs', 'script', 'base64', 'applet', 'alert', 'document',
            'write', 'cookie', 'window', 'confirm', 'prompt'
        );

        foreach ($words as $word)
        {
            $word = implode('\s*', str_split($word)).'\s*';

            // We only want to do this when it is followed by a non-word character
            // That way valid stuff like "dealer to" does not become "dealerto"
            $str = preg_replace_callback('#('.substr($word, 0, -3).')(\W)#is', array($this, '_compact_exploded_words'), $str);
        }

        /*
         * Remove disallowed Javascript in links or img tags
         * We used to do some version comparisons and use of stripos(),
         * but it is dog slow compared to these simplified non-capturing
         * preg_match(), especially if the pattern exists in the string
         *
         * Note: It was reported that not only space characters, but all in
         * the following pattern can be parsed as separators between a tag name
         * and its attributes: [\d\s"\'`;,\/\=\(\x00\x0B\x09\x0C]
         * ... however, remove_invisible_characters() above already strips the
         * hex-encoded ones, so we'll skip them below.
         */
        do
        {
            $original = $str;

            if (preg_match('/<a/i', $str))
            {
                $str = preg_replace_callback('#<a[^a-z0-9>]+([^>]*?)(?:>|$)#si', array($this, '_js_link_removal'), $str);
            }

            if (preg_match('/<img/i', $str))
            {
                $str = preg_replace_callback('#<img[^a-z0-9]+([^>]*?)(?:\s?/?>|$)#si', array($this, '_js_img_removal'), $str);
            }

            if (preg_match('/script|xss/i', $str))
            {
                $str = preg_replace('#</*(?:script|xss).*?>#si', '[removed]', $str);
            }
        }
        while ($original !== $str);

        unset($original);

        // Remove evil attributes such as style, onclick and xmlns
        $str = $this->_remove_evil_attributes($str, $is_image);

        /*
         * Sanitize naughty HTML elements
         *
         * If a tag containing any of the words in the list
         * below is found, the tag gets converted to entities.
         *
         * So this: <blink>
         * Becomes: &lt;blink&gt;
         */
        $naughty = 'alert|prompt|confirm|applet|audio|basefont|base|behavior|bgsound|blink|body|embed|expression|form|frameset|frame|head|html|ilayer|iframe|input|button|select|isindex|layer|link|meta|keygen|object|plaintext|style|script|textarea|title|math|video|svg|xml|xss';
        $str = preg_replace_callback('#<(/*\s*)('.$naughty.')([^><]*)([><]*)#is', array($this, '_sanitize_naughty_html'), $str);

        /*
         * Sanitize naughty scripting elements
         *
         * Similar to above, only instead of looking for
         * tags it looks for PHP and JavaScript commands
         * that are disallowed. Rather than removing the
         * code, it simply converts the parenthesis to entities
         * rendering the code un-executable.
         *
         * For example:    eval('some code')
         * Becomes:    eval&#40;'some code'&#41;
         */
        $str = preg_replace('#(alert|prompt|confirm|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)\((.*?)\)#si',
            '\\1\\2&#40;\\3&#41;',
            $str);

        // Final clean up
        // This adds a bit of extra precaution in case
        // something got through the above filters
        $str = $this->_do_never_allowed($str);

        /*
         * Images are Handled in a Special Way
         * - Essentially, we want to know that after all of the character
         * conversion is done whether any unwanted, likely XSS, code was found.
         * If not, we return TRUE, as the image is clean.
         * However, if the string post-conversion does not matched the
         * string post-removal of XSS, then it fails, as there was unwanted XSS
         * code found and removed/changed during processing.
         */
        if ($is_image === TRUE)
        {
            return ($str === $converted_string);
        }

        return $str;
    }
    /**
     * Remove Evil HTML Attributes (like event handlers and style)
     *
     * It removes the evil attribute and either:
     *
     *  - Everything up until a space. For example, everything between the pipes:
     *
     *    <code>
     *        <a |style=document.write('hello');alert('world');| class=link>
     *    </code>
     *
     *  - Everything inside the quotes. For example, everything between the pipes:
     *
     *    <code>
     *        <a |style="document.write('hello'); alert('world');"| class="link">
     *    </code>
     *
     * @param    string    $str        The string to check
     * @param    bool    $is_image    Whether the input is an image
     * @return    string    The string with the evil attributes removed
     */
    protected function _remove_evil_attributes($str, $is_image)
    {
        $evil_attributes = array('on\w*', 'style', 'xmlns', 'formaction', 'form', 'xlink:href', 'FSCommand', 'seekSegmentTime');

        if ($is_image === TRUE)
        {
            /*
             * Adobe Photoshop puts XML metadata into JFIF images,
             * including namespacing, so we have to allow this for images.
             */
            unset($evil_attributes[array_search('xmlns', $evil_attributes)]);
        }

        do {
            $count = $temp_count = 0;

            // replace occurrences of illegal attribute strings with quotes (042 and 047 are octal quotes)
            $str = preg_replace('/(<[^>]+)(?<!\w)('.implode('|', $evil_attributes).')\s*=\s*(\042|\047)([^\\2]*?)(\\2)/is', '$1[removed]', $str, -1, $temp_count);
            $count += $temp_count;

            // find occurrences of illegal attribute strings without quotes
            $str = preg_replace('/(<[^>]+)(?<!\w)('.implode('|', $evil_attributes).')\s*=\s*([^\s>]*)/is', '$1[removed]', $str, -1, $temp_count);
            $count += $temp_count;
        }
        while ($count);

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from the GET array
     *
     * @param    mixed    $index        Index for item to be fetched from $_GET
     * @param    bool    $xss_clean    Whether to apply XSS filtering
     * @return    mixed
     */
    public function get($index = NULL, $xss_clean = NULL)
    {
        return $this->_fetch_from_array($_GET, $index, $xss_clean);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from the POST array
     *
     * @param    mixed    $index        Index for item to be fetched from $_POST
     * @param    bool    $xss_clean    Whether to apply XSS filtering
     * @return    mixed
     */
    public function post($index = NULL, $xss_clean = NULL)
    {
        return $this->_fetch_from_array($_POST, $index, $xss_clean);
    }



    // --------------------------------------------------------------------

    /**
     * Fetch an item from the COOKIE array
     *
     * @param    mixed    $index        Index for item to be fetched from $_COOKIE
     * @param    bool    $xss_clean    Whether to apply XSS filtering
     * @return    mixed
     */
    public function cookie($index = NULL, $xss_clean = NULL)
    {
        return $this->_fetch_from_array($_COOKIE, $index, $xss_clean);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from the SERVER array
     *
     * @param    mixed    $index        Index for item to be fetched from $_SERVER
     * @param    bool    $xss_clean    Whether to apply XSS filtering
     * @return    mixed
     */
    public function server($index, $xss_clean = NULL)
    {
        return $this->_fetch_from_array($_SERVER, $index, $xss_clean);
    }

    // ------------------------------------------------------------------------

    /**
     * Set cookie
     *
     * Accepts an arbitrary number of parameters (up to 7) or an associative
     * array in the first parameter containing all the values.
     *
     * @param    string|mixed[]    $name        Cookie name or an array containing parameters
     * @param    string        $value        Cookie value
     * @param    int        $expire        Cookie expiration time in seconds
     * @param    string        $domain        Cookie domain (e.g.: '.yourdomain.com')
     * @param    string        $path        Cookie path (default: '/')
     * @param    string        $prefix        Cookie name prefix
     * @param    bool        $secure        Whether to only transfer cookies via SSL
     * @param    bool        $httponly    Whether to only makes the cookie accessible via HTTP (no javascript)
     * @return    void
     */
    public function set_cookie($name, $value = '', $expire = '', $domain = '', $path = '/', $prefix = '', $secure = FALSE, $httponly = FALSE)
    {
        if (is_array($name))
        {
            // always leave 'name' in last place, as the loop will break otherwise, due to $$item
            foreach (array('value', 'expire', 'domain', 'path', 'prefix', 'secure', 'httponly', 'name') as $item)
            {
                if (isset($name[$item]))
                {
                    $$item = $name[$item];
                }
            }
        }

        if ( ! is_numeric($expire))
        {
            $expire = time() - 86500;
        }
        else
        {
            $expire = ($expire > 0) ? time() + $expire : 0;
        }

        setcookie($prefix.$name, $value, $expire, $path, $domain, $secure, $httponly);
    }

}