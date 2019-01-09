<?php

namespace JazzMan\FormatHtml;

/**
 * Class Format.
 */
class FormatHtml
{
    /**
     * @var string
     */
    private $input;

    /**
     * @var string|null
     */
    private $output;

    /**
     * @var bool
     */
    private $in_tag = false;

    /**
     * @var bool
     */
    private $in_comment = false;

    /**
     * @var bool
     */
    private $in_content = false;

    /**
     * @var bool
     */
    private $inline_tag = false;

    /**
     * @var int
     */
    private $i = 0;

    /**
     * @var int
     */
    private $indent_depth = 0;

    /**
     * @var string
     */
    private $indent_type = "\t";

    /**
     * Fix HTML - Alias of process.
     *
     * @param string $input         HTML which is to be processed
     * @param bool   $use_spaces    Use spaces instead of tabs
     * @param int    $indent_length Length of indent spacing
     *
     * @return string
     */
    public function fix($input, $use_spaces = true, $indent_length = 4)
    {
        return $this->process($input, $use_spaces, $indent_length);
    }

    /**
     * Process HTML.
     *
     * @param string $input         HTML which is to be processed
     * @param bool   $use_spaces    Use spaces instead of tabs
     * @param int    $indent_length Length of indent spacing
     *
     * @return string
     */
    private function process($input, $use_spaces = true, $indent_length = 4)
    {
        if ($use_spaces) {
            $this->indent_type = str_repeat(' ', $indent_length);
        }

        $this->input = $input;
        $this->output = null;

        $i = 0;

        if (preg_match('/<\!doctype/i', $this->input)) {
            $i = strpos($this->input, '>') + 1;
            $this->output .= substr($this->input, 0, $i);
        }

        for ($this->i = $i, $loopsMax = \strlen($this->input); $this->i < $loopsMax; ++$this->i) {
            if ($this->in_comment) {
                $this->parseComment();
            } elseif ($this->in_tag) {
                $this->parseInnerTag();
            } elseif ($this->inline_tag) {
                $this->parseInnerInlineTag();
            } else {
                if (preg_match('/[\r\n\t]/', $this->input[$this->i])) {
                    continue;
                }

                if ('<' === $this->input[$this->i]) {
                    if (!$this->isInlineTag()) {
                        $this->in_content = false;
                    }
                    $this->parseTag();
                } elseif (!$this->in_content) {
                    if (!$this->inline_tag) {
                        $this->output .= "\n".str_repeat($this->indent_type, $this->indent_depth);
                    }
                    $this->in_content = true;
                }
                $this->output .= $this->input[$this->i];
            }
        }

        return $this->output;
    }

    private function parseComment()
    {
        if ($this->isEndComment()) {
            $this->in_comment = false;
            $this->output .= '-->';
            $this->i += 3;
        } else {
            $this->output .= $this->input[$this->i];
        }
    }

    /**
     * @return bool
     */
    private function isEndComment()
    {
        return '-' === $this->input[$this->i] && '-' === $this->input[$this->i + 1] && '>' === $this->input[$this->i + 2];
    }

    private function parseInnerTag()
    {
        if ('>' === $this->input[$this->i]) {
            $this->in_tag = false;
            $this->output .= '>';
        } else {
            $this->output .= $this->input[$this->i];
        }
    }

    private function parseInnerInlineTag()
    {
        if ('>' === $this->input[$this->i]) {
            $this->inline_tag = false;
            $this->decrementTabs();
            $this->output .= '>';
        } else {
            $this->output .= $this->input[$this->i];
        }
    }

    private function decrementTabs()
    {
        --$this->indent_depth;
        if ($this->indent_depth < 0) {
            $this->indent_depth = 0;
        }
    }

    /**
     * @return bool
     */
    private function isInlineTag()
    {
        $tags = [
            'title',
            'a',
            'span',
            'abbr',
            'acronym',
            'b',
            'basefont',
            'bdo',
            'big',
            'cite',
            'code',
            'dfn',
            'em',
            'font',
            'i',
            'kbd',
            'q',
            's',
            'samp',
            'small',
            'strike',
            'strong',
            'sub',
            'sup',
            'textarea',
            'tt',
            'u',
            'var',
            'del',
            'pre',
        ];

        $tag = '';

        for ($i = $this->i, $iMax = \strlen($this->input); $i < $iMax; ++$i) {
            if ('<' === $this->input[$i] || '/' === $this->input[$i]) {
                continue;
            }

            if (preg_match('/\s/', $this->input[$i]) || '>' === $this->input[$i]) {
                break;
            }

            $tag .= $this->input[$i];
        }

        if (\in_array($tag, $tags)) {
            return true;
        }

        return false;
    }

    private function parseTag()
    {
        if ($this->isComment()) {
            $this->output .= "\n".str_repeat($this->indent_type, $this->indent_depth);
            $this->in_comment = true;
        } elseif ($this->isEndTag()) {
            $this->in_tag = true;
            $this->inline_tag = false;
            $this->decrementTabs();
            if (!$this->isInlineTag() && !$this->isTagEmpty()) {
                $this->output .= "\n".str_repeat($this->indent_type, $this->indent_depth);
            }
        } else {
            $this->in_tag = true;
            if (!$this->in_content && !$this->inline_tag) {
                $this->output .= "\n".str_repeat($this->indent_type, $this->indent_depth);
            }
            if (!$this->isClosedTag()) {
                ++$this->indent_depth;
            }
            if ($this->isInlineTag()) {
                $this->inline_tag = true;
            }
        }
    }

    /**
     * @return bool
     */
    private function isComment()
    {
        return '<' === $this->input[$this->i] && '!' === $this->input[$this->i + 1] && '-' === $this->input[$this->i + 2] && '-' === $this->input[$this->i + 3];
    }

    /**
     * @return bool
     */
    private function isEndTag()
    {
        for ($i = $this->i, $iMax = \strlen($this->input); $i < $iMax; ++$i) {
            if ('<' === $this->input[$i] && '/' === $this->input[$i + 1]) {
                return true;
            }

            if ('<' === $this->input[$i] && '!' === $this->input[$i + 1]) {
                return true;
            }

            if ('>' === $this->input[$i]) {
                return false;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    private function isTagEmpty()
    {
        $tag = $this->getCurrentTag($this->i + 2);
        $in_tag = false;

        for ($i = $this->i - 1; $i >= 0; --$i) {
            if (!$in_tag) {
                if ('>' === $this->input[$i]) {
                    $in_tag = true;
                } elseif (!preg_match('/\s/', $this->input[$i])) {
                    return false;
                }
            } elseif ('<' === $this->input[$i]) {
                return $tag === $this->getCurrentTag($i + 1);
            }
        }

        return true;
    }

    /**
     * @param int $i String index of input
     *
     * @return string
     */
    private function getCurrentTag($i)
    {
        $tag = '';

        for ($iMax = \strlen($this->input); $i < $iMax; ++$i) {
            if ('<' === $this->input[$i]) {
                continue;
            }

            if ('>' === $this->input[$i] || preg_match('/\s/', $this->input[$i])) {
                return $tag;
            }

            $tag .= $this->input[$i];
        }

        return $tag;
    }

    /**
     * @return bool
     */
    private function isClosedTag()
    {
        $tags = [
            'meta',
            'link',
            'img',
            'hr',
            'br',
            'input',
        ];

        $tag = '';

        for ($i = $this->i, $iMax = \strlen($this->input); $i < $iMax; ++$i) {
            if ('<' === $this->input[$i]) {
                continue;
            }

            if (preg_match('/\s/', $this->input[$i])) {
                break;
            }

            $tag .= $this->input[$i];
        }

        if (\in_array($tag, $tags)) {
            return true;
        }

        return false;
    }
}
