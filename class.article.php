<?php
/**
 * Created by PhpStorm.
 * User: SAYRE_TS
 * Date: 2018-02-07
 * Time: 12:42 PM
 *
 * When constructing, pass in an array with these key and values
 * - string guid
 * - string name
 * - string display_name
 * - string path
 * - array placeholders
 */

namespace IDD\MCMSExport;

use DOMDocument;

class Article
{
    private $categories = '';
    private $content = '';
    private $contentOriginal = '';
    private $excerpt = '';
    private $images = '';
    private $mcmsGuid = '';
    private $path = '';
    private $postAuthor = '';
    private $postAuthorOriginal = '';
    private $postDate = '';
    private $postDateOriginal = '';
    private $postSlug = '';
    private $tags = '';
    private $title = '';
    private $titleOriginal = '';

    /**
     * class.article constructor.
     *
     * @param array $details
     */
    public function __construct($details)
    {

//        echo '<pre>';
//        print_r($details);
//        echo '</pre>';

        if ( ! is_array($details)) {
            die('$details must be an array');
        }

        if ( ! array_key_exists('guid', $details)) {
            die('$details must have a guid');
        }

        if ( ! array_key_exists('name', $details)) {
            die('$details must have a name');
        }

        if ( ! array_key_exists('display_name', $details)) {
            die('$details must have a display_name');
        }

        if ( ! array_key_exists('path', $details)) {
            die('$details must have a path');
        }

        if ( ! array_key_exists('placeholders', $details)) {
            die('$details must have placeholders');
        }

        if ( ! is_array($details['placeholders'])) {
            die('placeholders must be an array');
        }

        if ( ! array_key_exists('PH_headline', $details['placeholders'])) {
            die('placeholders must have PH_headline');
        }

        if ( ! array_key_exists('PH_article', $details['placeholders'])) {
            die('placeholders must have PH_article');
        }

        if ( ! array_key_exists('PH_contact', $details['placeholders'])) {
            die('placeholders must have PH_contact');
        }

        if ( ! array_key_exists('PH_date', $details['placeholders'])) {
            die('placeholders must have PH_date');
        }

        // MCMS GUID
        $this->setMcmsGuid($details['guid']);

        // Title
        $this->setTitleOriginal($details['placeholders']['PH_headline']['html']);
        $title = $this->getTitleOriginal();
        $title = html_entity_decode($title);
        $title = strip_tags($title);
        $title = self::allSpacesToSingleSpace($title);
        $title = htmlentities($title, ENT_QUOTES, 'UTF-8');
        $title = iconv('UTF-8', 'ASCII//TRANSLIT', $title);
        $title = self::removeNbsp($title);
        $title = trim($title);
        $this->setTitle($title);

        // Content
        $contentOriginal = $details['placeholders']['PH_article']['html'];
        $this->setContentOriginal($contentOriginal);
        $content = $contentOriginal;
        $content = htmlspecialchars_decode(htmlentities($content, ENT_QUOTES, 'UTF-8'));
        $content = iconv('UTF-8', 'ASCII//TRANSLIT', $content);
        $content = self::convertDivTagsToPTags($content);
        $content = self::changeTagNames($content, 'h1', 'h2');
        $content = self::cleanEmptyParagraphs($content);
        $content = self::cleanContentTags($content);
        $content = self::cleanContentAttributes($content);
        // Some a tags only had a name attribute and it was removed
        $content = self::removeUselessATags($content);
//        $content = self::removeUselessTableTags($content);
        $content = self::bodyHtml($content);
        $this->setContent($content);

        // Excerpt
        $excerpt = $this->getContentOriginal();
        $excerpt = iconv('UTF-8', 'ASCII//TRANSLIT', $excerpt);
        $excerpt = self::extractExcerpt($excerpt);
        $this->setExcerpt($excerpt);

        // Post Author
        $postAuthorOriginal = $details['placeholders']['PH_contact']['html'];
        $this->setPostAuthorOriginal($postAuthorOriginal);
        $postAuthor = $postAuthorOriginal;
        $postAuthor = iconv('UTF-8', 'ASCII//TRANSLIT', $postAuthor);
        $postAuthor = $this::extractPostAuthor($postAuthor);
        $this->setPostAuthor($postAuthor);

        // Post Date
        $this->setPostDateOriginal($details['placeholders']['PH_date']['text']);
        $this->setPostDate($this->cleanPostDate($this->getPostDateOriginal(),
            $details['name'],
            $details['path']));

        // Post Slug
        $this->setPostSlug($details['name']);

        // Categories
        $this->setCategories($this->extractCategories($this->getTitle()));

        // Tags (not being used)
        $this->setTags('');

        // Images
        $this->setImages($this->extractImageUrls($this->getContentOriginal()));
    }

    /**
     * @return string
     */
    public function getTitleOriginal(): string
    {
        return $this->titleOriginal;
    }

    /**
     * @param string $titleOriginal
     */
    public function setTitleOriginal(string $titleOriginal): void
    {
        $this->titleOriginal = $titleOriginal;
    }

    /**
     * This isn't exhaustive.
     *
     * @param string $corpus
     *
     * @return string
     */
    private static function allSpacesToSingleSpace($corpus)
    {
        $pattern = '/(?:&nbsp;|[\s\p{Z}\p{C}\x85\xA0\x{0085}\x{00A0}\x{FFFD}]+)+/u';
        $corpus  = preg_replace($pattern, ' ', $corpus);

        return $corpus;
    }

    /**
     * @param string $html
     *
     * @return string
     */
    private static function removeNbsp($html)
    {
        return str_replace('&nbsp;', '', $html);
    }

    /**
     * @param string $html
     *
     * @return string
     */
    private static function convertDivTagsToPTags($html)
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($html, LIBXML_COMPACT);
        $nodeListP   = $dom->getElementsByTagName('p');
        $nodeListDiv = $dom->getElementsByTagName('div');

//        echo '<pre>';
//        print_r($nodeListDiv);
//        echo '</pre>';

        if (0 == $nodeListP->length && 0 < $nodeListDiv->length) {
            self::changeTagNames($html, 'div', 'p');
        }

        $newHtml = $dom->saveHTML();
        $newHtml = self::bodyHtml($newHtml);

        return $newHtml;
    }

    /**
     * @param string $html
     * @param string $oldTagName
     * @param string $newTagName
     *
     * @return string
     */
    private static function changeTagNames($html, $oldTagName, $newTagName)
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($html, LIBXML_COMPACT);
        $nodeList = $dom->getElementsByTagName($oldTagName);

//        echo '<pre>';
//        print_r($nodeListDiv);
//        echo '</pre>';

        for ($i = $nodeList->length - 1; 0 <= $i; $i--) {
            self::changeTagName($nodeList->item($i), $newTagName);
        }

        $newHtml = $dom->saveHTML();
        $newHtml = self::bodyHtml($newHtml);

        return $newHtml;
    }

    /**
     * @param \DOMElement $oldNode
     * @param string $newTagName
     *
     * @return \DOMElement
     */
    private static function changeTagName($oldNode, $newTagName)
    {
        $oldNodeChildNodes = iterator_to_array($oldNode->childNodes);
        $newNode           = $oldNode->ownerDocument->createElement($newTagName);
        foreach ($oldNodeChildNodes as $oldNodeChildNode) {
            $oldNodeChildNodeCopy = $oldNode->ownerDocument->importNode($oldNodeChildNode, true);
            $newNode->appendChild($oldNodeChildNodeCopy);
        }
        if ($oldNode->hasAttributes()) {
            foreach ($oldNode->attributes as $oldNodeAttribute) {
                $oldNodeAttributeName  = $oldNodeAttribute->nodeName;
                $oldNodeAttributeValue = $oldNodeAttribute->nodeValue;
                $newNode->setAttribute($oldNodeAttributeName, $oldNodeAttributeValue);
            }
        }
        $oldNode->parentNode->replaceChild($newNode, $oldNode);

        return $newNode;
    }

    private static function bodyHtml($html)
    {
        $start = strpos($html, '<body>');
        if (false === $start) {
            return $html;
        } else {
            $start += 6;
        }
        $length = (strrpos($html, '</body>')) - strlen($html);

        return substr($html, $start, $length);
    }

    /**
     * This function is not the same as the drop-empty-paras option in Tidy
     * This takes into account whitespace.
     *
     * @param string $html
     *
     * @return string
     */
    private static function cleanEmptyParagraphs($html)
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($html, LIBXML_COMPACT);
        $nodeListP = $dom->getElementsByTagName('p');
        for ($i = $nodeListP->length - 1; 0 <= $i; $i--) {
            $p       = $nodeListP->item($i);
            $matches = array();
            preg_match('/\w/', $p->textContent, $matches);
            $hasWords = 0 < count($matches);
            if ( ! $hasWords) {
                $p->parentNode->removeChild($p);
            }
        }


        $newHtml = $dom->saveHTML();
        $newHtml = self::bodyHtml($newHtml);

        return $newHtml;
    }

    private static function cleanContentTags(string $html)
    {
        if (0 == strlen(trim($html))) {
            return '';
        }

        $allowableTags   = array();
        $allowableTags[] = '<a>';
        $allowableTags[] = '<b>';
        $allowableTags[] = '<br>';
        $allowableTags[] = '<em>';
        $allowableTags[] = '<h2>';
        $allowableTags[] = '<h3>';
        $allowableTags[] = '<h4>';
        $allowableTags[] = '<h5>';
        $allowableTags[] = '<h6>';
        $allowableTags[] = '<i>';
        $allowableTags[] = '<p>';
        $allowableTags[] = '<strong>';
        $allowableTags[] = '<table>';
        $allowableTags[] = '<tbody>';
        $allowableTags[] = '<td>';
        $allowableTags[] = '<thead>';
        $allowableTags[] = '<th>';
        $allowableTags[] = '<tr>';

        return $html = strip_tags($html, implode('', $allowableTags));
    }

    /*
     * This is a terrible and I'm ashamed of doing this
     */

    /**
     * @param string $html
     *
     * @return string
     */
    private static function cleanContentAttributes(string $html): string
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($html, LIBXML_COMPACT);
        $allElements = $dom->getElementsByTagName('*');
        /** @var \DOMElement $el */
        foreach ($allElements as $el) {

            $attributesToRemove = array();

            /** @var \DOMAttr $attr */
            foreach ($el->attributes as $attr) {
                if ( ! (('a' == $el->tagName && 'href' == $attr->nodeName) ||
                    ('td' == $el->tagName && 'colspan' == $attr->nodeName) ||
                    ('td' == $el->tagName && 'rowspan' == $attr->nodeName) ||
                    ('th' == $el->tagName && 'colspan' == $attr->nodeName) ||
                    ('th' == $el->tagName && 'rowspan' == $attr->nodeName)) ) {
                    $attributesToRemove[] = $attr;
                }
            }

            /** @var \DOMAttr $attr */
            foreach ($attributesToRemove as $attr) {
                $el->removeAttributeNode($attr);
            }

        }

        $newHtml = $dom->saveHTML();
        $newHtml = self::bodyHtml($newHtml);

        return $newHtml;
    }

    private static function removeUselessATags($html)
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($html, LIBXML_COMPACT);
        $nodeList = $dom->getElementsByTagName('a');

        for ($i = $nodeList->length - 1; 0 <= $i; $i--) {
            $el = $nodeList->item($i);
            if (0 === count(iterator_to_array($el->attributes))) {
                self::changeTagName($el, 'delete_me');
            }
        }

        $html = $dom->saveHTML();
        $html = self::cleanContentTags($html);

        $html = self::bodyHtml($html);

        return $html;

    }

    /**
     * I think this cleans content copy+pasted from another webpage
     * This maybe some of the worst code in my career
     *
     * @param string $htmlFragment
     *
     * @return string
     */
//    private static function removeUselessTableTags(string $htmlFragment): string
//    {
//        $dom = new DOMDocument;
//        @$dom->loadHTML($htmlFragment, LIBXML_COMPACT);
//        $body = $dom->getElementsByTagName('body')->item(0);
//        $children = $body->childNodes;
//        $hasRootTable = false;
//        $isWrappedInATable = false;
////        print_r($children);
//        /** @var \DOMElement $item */
//        foreach ($children as $item) {
////            print('<pre><code>');
////            print_r($item);
////            print('</code></pre>');
//            if ('table' == $item->tagName) {
//                $hasRootTable = true;
//            }
//        }
//        if ($hasRootTable) {
//            $isWrappedInATable = true;
//            foreach ($children as $item) {
//                if ('table' != $item->tagName && strlen(trim($item->textContent))) {
//                    $isWrappedInATable = false;
//                }
//            }
//        }
//        if ($isWrappedInATable) {
//            $htmlFragment = self::cleanAllTableTags($htmlFragment);
//        }
//        return $htmlFragment;
//    }

    /**
     * @param string $html
     *
     * @return string
     */
//    private static function removeUselessPTags(string $html): string
//    {
//        $dom = new DOMDocument;
//        @$dom->loadHTML($htmlFragment, LIBXML_COMPACT);
//        $tds = $dom->getElementsByTagName('td');
//        $ths = $dom->getElementsByTagName('th');
//        /** @var \DOMElement $td */
//        foreach ($tds as $td) {
//            $reduceToText = false;
//            /** @var \DOMElement $n */
//            foreach ($td->childNodes as $child_node) {
//                if ('p' == strtolower($child_node->nodeName)) {
//                    $reduceToText = true;
//                }
//            }
//            foreach ($td->childNodes as $child_node) {
//                if ('p' != strtolower($child_node->nodeName) && strlen(trim($child_node->textContent))) {
//                    $reduceToText = false;
//                }
//            }
//            if ($reduceToText) {
//                ;
//            }
//        }
//        foreach ($ths as $th) {
//
//        }
//    }

    /**
     * This code is shameful
     *
     * @param string $html
     *
     * @return string
     */
    private static function cleanAllTableTags(string $html): string
    {
        if (0 == strlen(trim($html))) {
            return '';
        }

        $allowableTags   = array();
        $allowableTags[] = '<a>';
        $allowableTags[] = '<b>';
        $allowableTags[] = '<br>';
        $allowableTags[] = '<em>';
        $allowableTags[] = '<h2>';
        $allowableTags[] = '<h3>';
        $allowableTags[] = '<h4>';
        $allowableTags[] = '<h5>';
        $allowableTags[] = '<h6>';
        $allowableTags[] = '<i>';
        $allowableTags[] = '<p>';
        $allowableTags[] = '<strong>';

        return $html = strip_tags($html, implode('', $allowableTags));
    }

    /**
     * @param \DOMNode $from
     */
//    private static function DOMRemove($from): void
//    {
//        $sibling = $from->firstChild;
//        do {
//            $next = $sibling->nextSibling;
//            $from->parentNode->insertBefore($sibling, $from);
//        } while ($sibling = $next);
//        $from->parentNode->removeChild($from);
//    }

    /**
     * @return string
     */
    public function getContentOriginal(): string
    {
        return $this->contentOriginal;
    }

    /**
     * @param string $contentOriginal
     */
    public function setContentOriginal(string $contentOriginal): void
    {
        $this->contentOriginal = $contentOriginal;
    }

    /**
     * @param string $html
     *
     * @return string
     */
    private static function extractExcerpt(string $html)
    {
        $excerpt = '';

        $dom = new DOMDocument;
        @$dom->loadHTML($html, LIBXML_COMPACT);

        $paragraphs = $dom->getElementsByTagName('p');

        if (0 < $paragraphs->length) {
            $excerpt = $paragraphs[0]->nodeValue;
        } else {
            $excerpt = strip_tags($html);
        }

        if (999 < strlen($excerpt)) {
            $excerpt = self::smartTruncate($excerpt, 999, '...', true);
        }

        return $excerpt;
    }

    private static function smartTruncate($text, $maxLength = 140, $cutOff = '...', $keepWord = false)
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        if (strlen($text) > $maxLength) {
            if ($keepWord) {
                $text = substr($text, 0, $maxLength + 1);

                if ($last_space = strrpos($text, ' ')) {
                    $text = substr($text, 0, $last_space);
                    $text = rtrim($text);
                    $text .= $cutOff;
                }
            } else {
                $text = substr($text, 0, $maxLength);
                $text = rtrim($text);
                $text .= $cutOff;
            }
        }

        return $text;
    }

    private static function extractPostAuthor($text)
    {
        $text   = strtolower(trim($text));
        $author = 'news';

        if (0 == strlen($text)) {
            return $author;
        }

        // Dave Beyer (beyer_wd)
        if (false !== strpos($text, 'beyer') && false !== strpos($text, 'dave')) {
            $author = 'beyer_wd';
        }

        // Larry Brumley (brumley_ld)
        if (false !== strpos($text, 'brumley') && false !== strpos($text, 'larry')) {
            $author = 'brumley_ld';
        }

        // Jenny Butkus (butkus_j)
        if (false !== strpos($text, 'butkus') && false !== strpos($text, 'jenny')) {
            $author = 'butkus_j';
        }

        // Richard Cameron (cameron_rl)
        if (false !== strpos($text, 'cameron') && false !== preg_match('/ric(hard|k)/', $text)) {
            $author = 'cameron_rl';
        }

        // Denise Cook (cook_d)
        if (false !== strpos($text, 'cook') && false !== strpos($text, 'denise')) {
            $author = 'cook_d';
        }

        // Steven Ericson (ericson_sp)
        if (false !== strpos($text, 'ericson') && false !== preg_match('/ste(ph|v)en/', $text)) {
            $author = 'ericson_sp';
        }

        // Elizabeth Flader (flader_e)
        if (false !== strpos($text, 'flader') && false !== strpos($text, 'elizabeth')) {
            $author = 'flader_e';
        }

        // Nancy Fullbright (fullbright_n)
        if (false !== strpos($text, 'fullbright') && false !== strpos($text, 'nancy')) {
            $author = 'fullbright_n';
        }

        // Wes Griffith (griffith_w)
        if (false !== strpos($text, 'griffith') && false !== strpos($text, 'wes')) {
            $author = 'griffith_w';
        }

        // David Hefner (hefner_dn)
        if (false !== strpos($text, 'hefner') && false !== strpos($text, 'david')) {
            $author = 'hefner_dn';
        }

        // Cindy Hill (hill_c)
        if (false !== strpos($text, 'hill') && false !== strpos($text, 'cindy')) {
            $author = 'hill_c';
        }

        // Brett Jarrett (jarrett_b)
        if (false !== strpos($text, 'jarrett') && false !== strpos($text, 'brett')) {
            $author = 'jarrett_b';
        }

        // Roban Johnson (johnson_r)
        if (false !== strpos($text, 'johnson') && false !== strpos($text, 'roban')) {
            $author = 'johnson_r';
        }

        // Randy Jones (jones_r3)
        if (false !== strpos($text, 'jones') && false !== strpos($text, 'randy')) {
            $author = 'jones_r3';
        }

        // Joel Lamp (lamp_j)
        if (false !== strpos($text, 'joel') && false !== strpos($text, 'lamp')) {
            $author = 'lamp_j';
        }

        // Judith Lunsford (lunsford_j)
        if (false !== strpos($text, 'lunsford') && false !== preg_match('/jud(ith|y)/', $text)) {
            $author = 'lunsford_j';
        }

        // (Lindsay M. Moss)
        if (false !== strpos($text, 'moss') && false !== strpos($text, 'lindsay')) {
            $author = 'moss_lm';
        }

        // Sonal Patel (patel_sd)
        if (false !== strpos($text, 'patel') && false !== strpos($text, 'sonal')) {
            $author = 'patel_sd';
        }

        // Andy Peters (peters_a)
        if (false !== strpos($text, 'peters') && false !== strpos($text, 'andy')) {
            $author = 'peters_a';
        }

        // Billie Rampley (rampley_bb)
        if (false !== strpos($text, 'rampley') && false !== strpos($text, 'billie')) {
            $author = 'rampley_bb';
        }

        // Dan Recupero (recupero_d)
        if (false !== strpos($text, 'recupero') && false !== strpos($text, 'dan')) {
            $author = 'recupero_d';
        }

        // Anna Sandison (sandison_aw)
        if (false !== strpos($text, 'sandison') && false !== strpos($text, 'anna')) {
            $author = 'sandison_aw';
        }

        // Mark Vanderhoek (vanderhoek_m)
        if (false !== strpos($text, 'vanderhoek') && false !== strpos($text, 'mark')) {
            $author = 'vanderhoek_m';
        }

        // Lance Wallace (wallace_l)
        if (false !== strpos($text, 'wallace') && false !== strpos($text, 'lance')) {
            $author = 'wallace_l';
        }

        return $author;
    }

    private static function cleanPostDate($date, $name, $path)
    {
        $parsed_date = date_parse($date);
        preg_match('/^\/news\/articles\/(\d{4})/i', $path, $path_matches);
        preg_match('/^(\d+)/i', $name, $name_matches);

//        echo '<pre>';
//        print_r($parsed_date);
//        echo '</pre>';

        // Year
        if ($parsed_date && 0 == $parsed_date['error_count'] && 0 < strlen($parsed_date['year'])) {
            $year = $parsed_date['year'];
        } elseif (isset($path_matches[1]) && 4 == strlen($path_matches[1])) {
            $year = $path_matches[1];
        } elseif (isset($name_matches[1]) && 6 == strlen($name_matches[1])) {
            $year = substr($name_matches[1], 0, 2);
            if ($year < 0) {
                $year = "20$year";
            } else {
                $year = "19$year";
            }
        } else {
            $year = '1900';
        }

        // Month
        if ($parsed_date && 0 == $parsed_date['error_count'] && 0 < strlen($parsed_date['month'])) {
            $month = str_pad($parsed_date['month'], 2, '0', STR_PAD_LEFT);
        } elseif (isset($name_matches[1]) && 6 == strlen($name_matches[1])) {
            $month = substr($name_matches[1], 2, 2);
        } elseif (isset($name_matches[1]) && 4 == strlen($name_matches[1])) {
            $month = substr($name_matches[1], 0, 2);
        } elseif (isset($name_matches[1]) && 2 == strlen($name_matches[1])) {
            $month = $name_matches[1];
        } else {
            $month = '01';
        }

        // Day
        if ($parsed_date && 0 == $parsed_date['error_count'] && 0 < strlen($parsed_date['day'])) {
            $day = str_pad($parsed_date['day'], 2, '0', STR_PAD_LEFT);
        } elseif (isset($name_matches[1]) && 6 == strlen($name_matches[1])) {
            $day = substr($name_matches[1], 4, 2);
        } elseif (isset($name_matches[1]) && 4 == strlen($name_matches[1])) {
            $day = substr($name_matches[1], 2, 2);
        } else {
            $day = '01';
        }

        // Last chance to deal with errors
        if ($year < 1900 || 2013 < $year) {
            $year = '1900';
        }

        if (12 < $month) {
            if ($day < 12 && $month < cal_days_in_month(CAL_GREGORIAN, $day, $year)) {
                $temp  = $month;
                $month = $day;
                $day   = $temp;
            } else {
                $month = '01';
            }
        }

        if (cal_days_in_month(CAL_GREGORIAN, $month, $year) < $day) {
            $day = '01';
        }

        return "$year-$month-$day";
    }

    /**
     * @return string
     */
    public function getPostDateOriginal(): string
    {
        return $this->postDateOriginal;
    }

    /**
     * @param string $postDateOriginal
     */
    public function setPostDateOriginal(string $postDateOriginal): void
    {
        $this->postDateOriginal = $postDateOriginal;
    }

    /**
     * @param $title string
     *
     * @return string
     */
    private static function extractCategories($title)
    {
        $title      = strtolower($title);
        $categories = array();

        if (false !== strpos($title, 'faculty') && false !== strpos($title, 'notables')) {
            $categories[] = 'Faculty Notables';
        } else {
            $categories[] = 'General';
        }

        return implode(', ', $categories);
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @param string $htmlFragment
     *
     * @return string
     */
    private static function extractImageUrls(string $htmlFragment)
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($htmlFragment, LIBXML_COMPACT);
        $imgTags   = $dom->getElementsByTagName('img');
        $imageUrls = array();

        /** @var \DOMElement $element */
        foreach ($imgTags as $element) {
            $src = $element->getattribute('src');
            if (0 <= strpos($src, 'spacer') || 0 <= strpos($src, '.gif')) {
                continue;
            }
            if (0 === strpos($src, 'http://') ||
                0 === strpos($src, 'https://') ||
                0 === strpos($src, '//')) {
                $imageUrls[] = $src;
            } else {
                $imageUrls[] = "http://archive.mercer.edu/www2/www2.mercer.edu{$src}\n";
            }
        }

        if (0 === count($imageUrls)) {
            $imageUrls[] = self::getRandomStockImage();
        }

        return implode(',', $imageUrls);
    }

    /**
     * Matt picked these images
     */
    private static function getRandomStockImage(): string
    {
        $imageUrls = array();

        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/DJI_0003.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/DJI_0036.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/DJI_0049.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/DJI_0060.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/DJI_0310.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/DSC_7697.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/DSC_7716.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/DSC_7722.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/DSC_8550.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/DSC_9141.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/MATT4582.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/MATT4596.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/MATT4751.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/MATT5589.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/MATT7896.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/MATT7907.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/MATT7945.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/MER_7373.jpg';
        $imageUrls[] = 'https://assets.mercer.edu/news-import-stock-images/MER_8635.jpg';

        return $imageUrls[array_rand($imageUrls)];

    }

    /**
     * @return string
     */
    public function getCategories(): string
    {
        return $this->categories;
    }

    /**
     * @param string $categories
     */
    public function setCategories(string $categories): void
    {
        $this->categories = $categories;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    /**
     * @return string
     */
    public function getExcerpt(): string
    {
        return $this->excerpt;
    }

    /**
     * @param string $excerpt
     */
    public function setExcerpt(string $excerpt): void
    {
        $this->excerpt = $excerpt;
    }

    /**
     * @return string
     */
    public function getImages(): string
    {
        return $this->images;
    }

    /**
     * @param string $images
     */
    public function setImages(string $images): void
    {
        $this->images = $images;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    /**
     * @return string
     */
    public function getPostAuthor(): string
    {
        return $this->postAuthor;
    }

    /**
     * @param string $postAuthor
     */
    public function setPostAuthor(string $postAuthor): void
    {
        $this->postAuthor = $postAuthor;
    }

    /**
     * @return string
     */
    public function getPostAuthorOriginal(): string
    {
        return $this->postAuthorOriginal;
    }

    /**
     * @param string $postAuthorOriginal
     */
    public function setPostAuthorOriginal(string $postAuthorOriginal): void
    {
        $this->postAuthorOriginal = $postAuthorOriginal;
    }

    /**
     * @return string
     */
    public function getPostDate(): string
    {
        return $this->postDate;
    }

    /**
     * @param string $postDate
     */
    public function setPostDate(string $postDate): void
    {
        $this->postDate = $postDate;
    }

    /**
     * @return string
     */
    public function getPostSlug(): string
    {
        return $this->postSlug;
    }

    /**
     * @param string $postSlug
     */
    public function setPostSlug(string $postSlug): void
    {
        $this->postSlug = $postSlug;
    }

    /**
     * @return string
     */
    public function getTags(): string
    {
        return $this->tags;
    }

    /**
     * @param string $tags
     */
    public function setTags(string $tags): void
    {
        $this->tags = $tags;
    }

    /**
     * @return string
     */
    public function getMcmsGuid(): string
    {
        return $this->mcmsGuid;
    }

    /**
     * @param string $mcmsGuid
     */
    public function setMcmsGuid(string $mcmsGuid): void
    {
        $this->mcmsGuid = $mcmsGuid;
    }

}