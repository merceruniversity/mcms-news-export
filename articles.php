<?php
/**
 * Created by PhpStorm.
 * User: SAYRE_TS
 * Date: 2017-09-19
 * Time: 3:28 PM
 *
 * Sample image URL
 * http://archive.mercer.edu/www2/www2.mercer.edu/NR/rdonlyres/65396474-EAE5-4E48-8B8B-DC626BD17560/0/Bell_Family.jpg
 *
 */

require './Encoding.php';
use \ForceUTF8\Encoding;

class Articles
{

    // private $serverName = 'bubbleyum,2136';
	// private $databaseName = 'ede';
    private $serverName = 'localhost';
	private $connectionOptions = array('Database' => 'mcms-export', 'CharacterSet' => 'UTF-8');

    /* Functions: Public */

  function get ()
  {

    /* Connect using Windows Authentication. */
    $conn = sqlsrv_connect( $this->serverName, $this->connectionOptions );

    $sql = <<<SQL
           SELECT postings.[guid]       AS posting_guid,
                  postings.name         AS posting_name,
                  postings.display_name AS posting_display_name,
                  postings.[path]       AS posting_path,
                  placeholders.name     AS placeholder_name,
                  placeholders.[type]   AS placeholder_type,
                  placeholders.html     AS placeholder_html,
                  placeholders.[text]   AS placeholder_text
             FROM postings
        LEFT JOIN placeholders
               ON postings.guid = placeholders.posting_guid
            WHERE 1=1
--               AND postings.[path] LIKE '/news/%'
--               AND postings.guid IN
--                   (
--                       SELECT TOP 100 postings.guid
--                         FROM postings
--                        WHERE postings.[path] LIKE '/news/%'
--                     ORDER BY NEWID()
--                   )
--               AND postings.guid IN (
--                        -- Title ends in capital A circumflex
--                        '14151E1C-7C07-4FA7-B79C-12869D0BC177',
--                        '1F6C0185-A3D4-4B13-AA96-440FF2A4444D',
--                        -- Title has lowercase a circumflex Euro TM
--                        'EA277333-9279-4048-A8F4-DD480986B4A3',
--                        '6598359B-B1EE-463E-9E3B-85DE1B34C3A5',
--                        'A0926DFA-79A7-47C2-BA74-513B1E36D205',
--                        -- Title has misplaced interrogation point
--                        '4EAB1913-530A-4CF7-8621-83C0403AC0C8',
--                        -- Body has wrong e aigu accent
--                        '6AC60A98-9757-416F-B354-BC93610372D0',
--                        -- Um... lots
--                        'A9A8E3C5-9D9F-4320-A1B7-AF7ADBA9B3F3'
--                   )
              AND postings.guid IN (
                         SELECT TOP 10 po2.guid
                           FROM postings po2
                      LEFT JOIN placeholders pl2
                             ON po2.guid = pl2.posting_guid
                          WHERE po2.[path] LIKE '/news/%'
                            AND pl2.html LIKE '%###%'
                  )
         ORDER BY posting_name ASC
SQL;

    if ($conn === false)
    {
      echo 'Unable to connect.</br>';
      die( print_r( sqlsrv_errors(), true ) );
    }

    $stmt = sqlsrv_query( $conn, $sql );

    if ($stmt === false)
    {
      echo 'Error in executing query.</br>';
      die( print_r( sqlsrv_errors(),true ) );
    }

    $arr = $this->articles_stmt_to_grouped_arr($stmt);

//        print_r( $arr );

    $arr = $this->grouped_arr_to_importable_arr($arr);

//        print_r( $arr );

    /* Free statement and connection resources. */
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

    return $arr;
  }

    /* Functions: Private */

    private function articles_stmt_to_grouped_arr($stmt)
    {
        $arr = array();

        while ($row = sqlsrv_fetch_array($stmt))
        {
            $posting_guid = $row['posting_guid'];
            $placeholder_name = $row['placeholder_name'];

            // Posting
            if (!isset($arr[$posting_guid]))
            {
                $arr[$posting_guid] = array();
            }

            // Posting::Placeholders
            if (!isset($arr[$posting_guid]['placeholders']))
            {
                $arr[$posting_guid]['placeholders'] = array();
            }

            // Posting::Placeholders::Placeholder
            if (!isset($arr[$posting_guid]['placeholders'][$row['placeholder_name']]))
            {
                $arr[$posting_guid]['placeholders'][$placeholder_name] = array();
            }

            // Posting::Display Name
            if (!isset($arr[$posting_guid]['display_name']))
            {
                $val = Encoding::toUTF8($row['posting_display_name']);
                $arr[$posting_guid]['display_name'] = $val;
            }

            // Posting::Name
            if (!isset($arr[$posting_guid]['name']))
            {
                $val = Encoding::toUTF8($row['posting_name']);
                $arr[$posting_guid]['name'] = $val;
            }

            // Posting::Path
            if (!isset($arr[$posting_guid]['path']))
            {
                $val = Encoding::toUTF8($row['posting_path']);
                $arr[$posting_guid]['path'] = $val;
            }

            // Posting::Placeholders::Placeholder::HTML
            if (!isset($arr[$posting_guid]['placeholders'][$placeholder_name]['html']))
            {
                $val = Encoding::toUTF8($row['placeholder_html']);
                $arr[$posting_guid]['placeholders'][$placeholder_name]['html'] = $val;
                // $arr[$posting_guid]['placeholders'][$placeholder_name]['html'] = $row['placeholder_html'];
            }

            // Posting::Placeholders::Placeholder::Text
            if (!isset($arr[$posting_guid]['placeholders'][$row['placeholder_name']]['text']))
            {
                $val = Encoding::toUTF8($row['placeholder_text']);
                $arr[$posting_guid]['placeholders'][$placeholder_name]['text'] = $val;
            }

            // Posting::Placeholders::Placeholder::Type
            if (!isset($arr[$posting_guid]['placeholders'][$row['placeholder_name']]['type']))
            {
                $val = Encoding::toUTF8($row['placeholder_type']);
                $arr[$posting_guid]['placeholders'][$placeholder_name]['type'] = $val;
            }
        }

        return $arr;
    }

  private function clean_author ($text)
  {
    $text = str_replace(',',' ', $text);

    $text = preg_replace('/\(\d{3}(\)|\()\s*\d{3}\s*-\s*?\d{4}/i',      ' ', $text);
    $text = preg_replace('/\(?\d{3}\s*(-|\/)\s*\d{3}\s*-\s*\d{4}\)?/i', ' ', $text);
    $text = preg_replace('/event contacts?\s*:?/i',                     ' ', $text);
    $text = preg_replace('/media contacts?\s*:?/i',                     ' ', $text);
    $text = preg_replace('/media advisorys?\s*:?/i',                    ' ', $text);
    $text = preg_replace('/registration contacts?\s*:?/i',              ' ', $text);
    $text = preg_replace('/session contacts?\s*:?/i',                   ' ', $text);
    $text = preg_replace('/ext(.?|\s+|.?\s+)\d+/i',                     ' ', $text);
    $text = preg_replace('/contact\s*:?/i',                             ' ', $text);
    $text = preg_replace('/ticket\s+sales?:?/i',                        ' ', $text);
    $text = preg_replace('/or\s*$/i',                                   ' ', $text);

    $text = preg_replace('/\s+/',' ', $text);

    $text = trim($text);

    return $text;
  }

  private function extract_date($date, $name, $path)
  {
    $parsed_date  = date_parse($date);
    preg_match('/^\/news\/articles\/(\d{4})/i', $path, $path_matches);
    preg_match('/^(\d+)/i', $name, $name_matches);

//        echo '<pre>';
//        print_r($parsed_date);
//        echo '</pre>';

    // Year
    if ( $parsed_date && 0 == $parsed_date['error_count'] && 0 < strlen($parsed_date['year']) )
    {
      $year = $parsed_date['year'];
    }
    else if ( isset($path_matches[1]) && 4 == strlen($path_matches[1]) )
    {
      $year = $path_matches[1];
    }
    else if ( isset($name_matches[1]) && 6 == strlen($name_matches[1]) )
    {
      $year  = substr($name_matches[1], 0, 2);
      if ($year < 0)
      {
        $year = "20$year";
      }
      else
      {
        $year = "19$year";
      }
    }
    else
    {
      $year = '1900';
    }

    // Month
    if ( $parsed_date && 0 == $parsed_date['error_count'] && 0 < strlen($parsed_date['month']) )
    {
      $month = str_pad($parsed_date['month'], 2, '0', STR_PAD_LEFT);
    }
    else if ( isset($name_matches[1]) && 6 == strlen($name_matches[1]) )
    {
      $month = substr($name_matches[1], 2, 2);
    }
    else if ( isset($name_matches[1]) && 4 == strlen($name_matches[1]) )
    {
      $month = substr($name_matches[1], 0, 2);
    }
    else if ( isset($name_matches[1]) && 2 == strlen($name_matches[1]) )
    {
      $month = $name_matches[1];
    }
    else
    {
      $month = '01';
    }

    // Day
    if ( $parsed_date && 0 == $parsed_date['error_count']  && 0 < strlen($parsed_date['day']) )
    {
      $day = str_pad($parsed_date['day'], 2, '0', STR_PAD_LEFT);
    }
    else if ( isset($name_matches[1]) && 6 == strlen($name_matches[1]) )
    {
      $day = substr($name_matches[1], 4, 2);
    }
    else if ( isset($name_matches[1]) && 4 == strlen($name_matches[1]) )
    {
      $day = substr($name_matches[1], 2, 2);
    }
    else
    {
      $day = '01';
    }

    // Last chance to deal with errors
    if ( $year < 1900 || 2013 < $year )
    {
      $year = '1900';
    }

    if ( 12 < $month )
    {
      if ( $day < 12 && $month < cal_days_in_month(CAL_GREGORIAN, $day, $year) ) {
        $temp  = $month;
        $month = $day;
        $day   = $temp;
      } else {
        $month = '01';
      }
    }

    if ( cal_days_in_month(CAL_GREGORIAN, $month, $year) < $day )
    {
      $day = '01';
    }

    return "$year-$month-$day";
  }

    private function extract_excerpt(string $html_fragment)
    {
		// Old articles used this to demarcate the 'cut'
    	$at_at_at_pos = strpos($html_fragment, '@@@');

	    if (false !== $at_at_at_pos) {
		    return strip_tags(substr($html_fragment, 0, $at_at_at_pos));
        }

	    $doc = new DOMDocument;
	    @$doc->loadHTML(htmlspecialchars_decode(htmlentities(html_entity_decode($html_fragment))));

    	$paragraphs = $doc->getElementsByTagName('p');

        if (0 < $paragraphs->length) {
            $excerpt = $paragraphs[0]->nodeValue;
            if (0 < strlen(trim($excerpt)))
            {
	            return strip_tags( $excerpt );
            }
        }

        $allElements = $doc->getElementsByTagName('*');

        if (0 < $allElements->length) {
            $excerpt = $allElements[0]->nodeValue;
            if (0 < strlen(trim($excerpt)))
            {
	            return strip_tags($excerpt);
            }
        }

        return '';
    }

    private function extract_author ($text) {
        $text = strtolower(trim($text));
        $author = 'news';

        if (0 == strlen($text)) {
            return $author;
        }

        // Dave Beyer (beyer_wd)
        if ( false !== strpos($text, 'beyer') && false !== strpos($text, 'dave') ) {
            $author = 'beyer_wd';
        }

        // Larry Brumley (brumley_ld)
        if ( false !== strpos($text, 'brumley') && false !== strpos($text, 'larry') ) {
            $author = 'brumley_ld';
        }

        // Jenny Butkus (butkus_j)
        if ( false !== strpos($text, 'butkus') && false !== strpos($text, 'jenny') ) {
            $author = 'butkus_j';
        }

        // Richard Cameron (cameron_rl)
        if ( false !== strpos($text, 'cameron') && false !== preg_match('/ric(hard|k)/', $text) ) {
            $author = 'cameron_rl';
        }

        // Denise Cook (cook_d)
        if ( false !== strpos($text, 'cook') && false !== strpos($text, 'denise') ) {
            $author = 'cook_d';
        }

        // Steven Ericson (ericson_sp)
        if ( false !== strpos($text, 'ericson') && false !== preg_match('/ste(ph|v)en/', $text) ) {
            $author = 'ericson_sp';
        }

        // Elizabeth Flader (flader_e)
        if ( false !== strpos($text, 'flader') && false !== strpos($text, 'elizabeth') ) {
            $author = 'flader_e';
        }

        // Nancy Fullbright (fullbright_n)
        if ( false !== strpos($text, 'fullbright') && false !== strpos($text, 'nancy') ) {
            $author = 'fullbright_n';
        }

        // Wes Griffith (griffith_w)
        if ( false !== strpos($text, 'griffith') && false !== strpos($text, 'wes') ) {
            $author = 'griffith_w';
        }

        // David Hefner (hefner_dn)
        if ( false !== strpos($text, 'hefner') && false !== strpos($text, 'david') ) {
            $author = 'hefner_dn';
        }

        // Cindy Hill (hill_c)
        if ( false !== strpos($text, 'hill') && false !== strpos($text, 'cindy') ) {
            $author = 'hill_c';
        }

        // Brett Jarrett (jarrett_b)
        if ( false !== strpos($text, 'jarrett') && false !== strpos($text, 'brett') ) {
            $author = 'jarrett_b';
        }

        // Roban Johnson (johnson_r)
        if ( false !== strpos($text, 'johnson') && false !== strpos($text, 'roban') ) {
            $author = 'johnson_r';
        }

        // Randy Jones (jones_r3)
        if ( false !== strpos($text, 'jones') && false !== strpos($text, 'randy') ) {
            $author = 'jones_r3';
        }

        // Joel Lamp (lamp_j)
        if ( false !== strpos($text, 'joel') && false !== strpos($text, 'lamp') ) {
            $author = 'lamp_j';
        }

        // Judith Lunsford (lunsford_j)
        if ( false !== strpos($text, 'lunsford') && false !== preg_match('/jud(ith|y)/', $text) ) {
            $author = 'lunsford_j';
        }

        // (Lindsay M. Moss)
        if ( false !== strpos($text, 'moss') && false !== strpos($text, 'lindsay') ) {
            $author = 'moss_lm';
        }

        // Sonal Patel (patel_sd)
        if ( false !== strpos($text, 'patel') && false !== strpos($text, 'sonal') ) {
            $author = 'patel_sd';
        }

        // Andy Peters (peters_a)
        if ( false !== strpos($text, 'peters') && false !== strpos($text, 'andy') ) {
            $author = 'peters_a';
        }

        // Billie Rampley (rampley_bb)
        if ( false !== strpos($text, 'rampley') && false !== strpos($text, 'billie') ) {
            $author = 'rampley_bb';
        }

        // Dan Recupero (recupero_d)
        if ( false !== strpos($text, 'recupero') && false !== strpos($text, 'dan') ) {
            $author = 'recupero_d';
        }

        // Anna Sandison (sandison_aw)
        if ( false !== strpos($text, 'sandison') && false !== strpos($text, 'anna') ) {
            $author = 'sandison_aw';
        }

        // Mark Vanderhoek (vanderhoek_m)
        if ( false !== strpos($text, 'vanderhoek') && false !== strpos($text, 'mark') ) {
            $author = 'vanderhoek_m';
        }

        // Lance Wallace (wallace_l)
        if ( false !== strpos($text, 'wallace') && false !== strpos($text, 'lance') ) {
            $author = 'wallace_l';
        }

        return $author;
    }

    private function extract_image_urls(string $html_fragment)
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($html_fragment);
        $elements = $dom->getElementsByTagName('img');
        $images = '';
        foreach ($elements as $element)
        {
            $src = $element->getattribute('src');
            $images .= "http://archive.mercer.edu/www2/www2.mercer.edu{$src}\n";
        }
        return $images;
    }

    private function grouped_arr_to_importable_arr($groupedArr)
    {
        $arr = array();

        foreach ($groupedArr as $guid => $article)
        {
            if (!isset($article['placeholders']['PH_article']))
            {
                continue;
            }

            // $importable_article_html = $this->html_fragment_clean($article['placeholders']['PH_article']['html']);
            $importable_article_html = $article['placeholders']['PH_article']['html'];
            $importable_article_html = clean_article_html($importable_article_html);
            $importable_article_html = htmlentities($importable_article_html);
            $importable_headline_text = $this->convert_ascii($article['placeholders']['PH_headline']['text']);

            // echo '<div>';
            // echo $importable_headline_text;
            // for( $i = 0; $i <= strlen($importable_headline_text); $i++ ) {
            //     $char = substr( $importable_headline_text, $i, 1 );
            //     $ord = ord($char);
            //     echo "<div><code>${char}</code> = ${ord}</div>";
            //     // $char contains the current character, so do your processing here
            // }
            // echo '</div>';

            $importable_arr                      = array();
            $importable_arr['categories']        = 'General';
            $importable_arr['content']           = trim($importable_article_html);
            $importable_arr['content_raw']       = trim($article['placeholders']['PH_article']['html']);
            $importable_arr['excerpt']           = trim($this->extract_excerpt($article['placeholders']['PH_article']['html']));
            $importable_arr['images']            = trim($this->extract_image_urls($article['placeholders']['PH_article']['html']));
            $importable_arr['path']              = $article['path'];
            $importable_arr['post_author']       = trim($this->extract_author($article['placeholders']['PH_contact']['text']));
            $importable_arr['post_author_clean'] = trim($this->clean_author($article['placeholders']['PH_contact']['text']));
            $importable_arr['post_author_raw']   = trim($article['placeholders']['PH_contact']['text']);
            $importable_arr['post_date']         = trim($this->extract_date($article['placeholders']['PH_date']['text'], $article['name'], $article['path']));
            $importable_arr['post_date_raw']     = trim($article['placeholders']['PH_date']['text']);
            $importable_arr['post_slug']         = trim($article['name']);
            $importable_arr['tags']              = '';
            $importable_arr['title']             = trim($importable_headline_text);
            $importable_arr['unique_identifier'] = trim($guid);

            $arr[] = $importable_arr;
        }

        return $arr;
    }

    private function html_fragment_clean_pre (string $html_fragment) {
        $tidy_config = array(
            'bare'                        => true,
            'clean'                       => true,
            'drop-font-tags'              => true,
            'drop-proprietary-attributes' => true,
            'join-classes'                => true,
            'logical-emphasis'            => true,
            'merge-divs'                  => true,
            'merge-spans'                 => true,
            'quote-marks'                 => true,
            'quote-nbsp'                  => true,
            'show-body-only'              => true,
            'word-2000'                   => true,
            'wrap'                        => 0
        );
        $tidy = tidy_parse_string($html_fragment, $tidy_config, 'UTF8');
        $tidy->cleanRepair();
        return $tidy->body();
    }

    private function html_fragment_clean_post (string $html_fragment) {
        $tidy_config = array(
            'output-html'                 => true,
            'show-body-only'              => true,
            'wrap'                        => 0,
        );
        $tidy = tidy_parse_string($html_fragment, $tidy_config, 'UTF8');
        $tidy->cleanRepair();
        return preg_replace('/<\/?body>/', '', $tidy->body());
    }

    private function html_fragment_clean_attributes (string $html_fragment) {
        $dom = new DOMDocument;
        @$dom->loadHTML($html_fragment);
        $elements = $dom->getElementsByTagName('*');
        foreach ($elements as $element)
        {
//        echo '<pre>';
//        print_r($element);
//        echo '</pre>';
            foreach ($element->attributes as $attribute)
            {
//            echo '<pre>';
//            print_r($attribute);
//            echo '</pre>';
                if (('a' !== $element->tagName && 'href' !== $attribute->name) ||
                    ('img' !== $element->tagName && 'src' !== $attribute->name))
                {
                    $element->removeAttribute($attribute->nodeName);
                }
            }
        }
        $body_node = $dom->getElementsByTagName('body')->item(0);
        $html_fragment = $dom->saveHTML($body_node);
        $html_fragment = preg_replace('/<\/?body>/', '', $html_fragment);
        return $html_fragment;
    }

    private function html_fragment_clean_tags (string $html_fragment) {
        if (0 == strlen(trim($html_fragment)))
        {
            return '';
        }
        return $html_fragment = strip_tags($html_fragment, '<a><b><em><i><p><strong>');
    }

    private function convert_ascii (string $string) {
        // Replace Single Curly Quotes
        $search[]  = chr(226).chr(128).chr(152);
        $replace[] = "'";
        $search[]  = chr(226).chr(128).chr(153);
        $replace[] = "'";

        // Replace Smart Double Curly Quotes
        $search[]  = chr(226).chr(128).chr(156);
        $replace[] = '"';
        $search[]  = chr(226).chr(128).chr(157);
        $replace[] = '"';

        // Replace En Dash
        $search[]  = chr(226).chr(128).chr(147);
        $replace[] = '--';

        // Replace Em Dash
        $search[]  = chr(226).chr(128).chr(148);
        $replace[] = '---';

        // Replace Bullet
        $search[]  = chr(226).chr(128).chr(162);
        $replace[] = '*';

        // Replace Middle Dot
        $search[]  = chr(194).chr(183);
        $replace[] = '*';

        // Replace Ellipsis with three consecutive dots
        $search[]  = chr(226).chr(128).chr(166);
        $replace[] = '...';

        // Replace Non-breaking space with regular space
        $search[]  = chr(194).chr(160);
        $replace[] = ' ';

        // Replace Carriage Return + Line Feed
        $search[] = chr(13).chr(10);
        $replace[] = '';

        // Replace Line Feed
        $search[] = chr(10);
        $replace[] = '';

        // Replace Carriage Return
        $search[] = chr(13);
        $replace[] = '';

        // Apply Replacements
        $string = str_replace($search, $replace, $string);

        // Remove any non-ASCII Characters
        $string = preg_replace("/[^\x01-\x7F]/","", $string);

        return $string;
    }

    private function html_fragment_clean(string $html_fragment)
    {
        if (0 === strlen(trim($html_fragment)))
        {
            return '';
        }
        $html_fragment = htmlentities($html_fragment);
        if (0 === strlen(trim($html_fragment)))
        {
            return '';
        }
        $html_fragment = $this->convert_ascii($html_fragment);
        if (0 === strlen(trim($html_fragment)))
        {
            return '';
        }
        $html_fragment = $this->html_fragment_clean_pre($html_fragment);
        if (0 === strlen(trim($html_fragment)))
        {
            return '';
        }
        // $html_fragment = $this->html_fragment_clean_entities($html_fragment);
        // if (0 === strlen(trim($html_fragment)))
        // {
        //     return '';
        // }
        $html_fragment = $this->html_fragment_clean_tags($html_fragment);
        if (0 === strlen(trim($html_fragment)))
        {
            return '';
        }
        $html_fragment = $this->html_fragment_clean_attributes($html_fragment);
        if (0 === strlen(trim($html_fragment)))
        {
            return '';
        }
        $html_fragment = $this->html_fragment_clean_post($html_fragment);
        if (0 === strlen(trim($html_fragment)))
        {
            return '';
        }
        return $html_fragment;
    }

}
