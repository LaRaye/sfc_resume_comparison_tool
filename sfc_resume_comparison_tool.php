<?php

/**
 * @package SFCResumeComparisonTool
 */

/**
 * Plugin Name: SFC Resume Comparison Tool
 * Plugin URI: https://www.rcsprague.com/
 * Description: Tool that allows users to compare a resume file and a job description file to get the percentage match.
 * Version: 1.0
 * Requires PHP: 7.4
 * Author: LaRaye Johnson
 * Author URI: https://github.com/LaRaye
 * License: GPLv2 or later 
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sfc_resume_comparison_tool
 */

/*
SFC Resume Comparison Tool is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

SFC Resume Comparison Tool is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with SFC Resume Comparison Tool. If not, see {https://www.gnu.org/licenses/gpl-2.0.html}.
 */


// Make sure we don't expose any info if called directly
if (!function_exists('add_action')) {
    die;
}

// protect against invalid access
if (!defined('ABSPATH')) {
    die;
}

// Load PHPWord, and Smalot libraries via Composer autoloader
require 'vendor/autoload.php';

// Register main hooks for plugin
register_activation_hook(__FILE__, 'sfc_resume_comparison_tool_activate');
function sfc_resume_comparison_tool_activate()
{
    // nothing for now
}

register_deactivation_hook(__FILE__, 'sfc_resume_comparison_tool_deactivate');
function sfc_resume_comparison_tool_deactivate()
{
    // nothing for now
}

register_uninstall_hook(__FILE__, 'sfc_resume_comparison_tool_uninstall');
function sfc_resume_comparison_tool_uninstall()
{
    // nothing for now
}

// Render form for shortcode
function sfc_resume_comparison_tool_form()
{
    $nonce = wp_create_nonce('sfc_resume_comparison_tool_nonce');

    ob_start();

?>
    <div class="sfc_resume_comparison_tool_shortcode">
        <h3 class="title" >SFC Resume Comparison Tool</h3>
        <br>
        <h4>Would you like to see how well your current resume matches a potential role? Upload your resume and job posting below for results:</h4>
        <form id="sfc-resume-comparison-tool-form" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="sfc_resume_comparison_tool">
            <input type="hidden" name="sfc_resume_comparison_tool_nonce" value="<?php echo esc_attr($nonce); ?>">
            <div class="form-group">
                <label for="resume_file"><?php esc_html_e('Your Resume File:', 'sfc_resume_comparison_tool'); ?></label>
                <input type="file" id="resume_file" name="<?php echo esc_attr('resume_file'); ?>" accept="<?php echo esc_attr('.doc, .docx, .pdf, .txt'); ?>" class="form-control-file" aria-describedby="resume_file_description">
                <br><small id="resume_file_description" class="form-text text-muted">Choose a file in .doc, .docx, .pdf, or .txt format.</small>
            </div>
            <br>
            <div class="form-group">
                <label for="job_description_file"><?php esc_html_e('Your Job Description File:', 'sfc_resume_comparison_tool'); ?></label>
                <input type="file" id="job_description_file" name="<?php echo esc_attr('job_description_file'); ?>" accept="<?php echo esc_attr('.doc, .docx, .pdf, .txt'); ?>" class="form-control-file" aria-describedby="job_description_file_description">
                <br><small id="job_description_file_description" class="form-text text-muted">Choose a file in .doc, .docx, .pdf, or .txt format.</small>
            </div>
            <br>
            <input type="submit" id="submit" name="submit" value="<?php esc_html_e('Compare', 'sfc_resume_comparison_tool'); ?>" class="action-button">
            <input type="reset" id="reset" name="reset" value="<?php esc_html_e('Reset', 'sfc_resume_comparison_tool'); ?>" class="action-button">
        </form>

        <div id="sfc-resume-comparison-tool-results"></div>
    </div>
<?php

    return ob_get_clean();
}

/**
 * Display a shortcode to render the resume comparison tool form.
 *
 * @return string Output buffer contents.
 */
function sfc_resume_comparison_tool_shortcode()
{
    return sfc_resume_comparison_tool_form();
}

// Enqueue scripts
function sfc_resume_comparison_tool_enqueue_scripts()
{
    // Enqueue css
    wp_enqueue_style('sfc_style_css', plugin_dir_url(__FILE__) . 'assets/css/sfc_style.css');

    // Enqueue jQuery
    wp_enqueue_script('jquery');

    // Enqueue the sfc_handle_results JS file
    wp_enqueue_script('sfc_handle_results_js', plugin_dir_url(__FILE__) . 'assets/js/sfc_handle_results.js', array('jquery'), '1.0.0', true);

    $ajax_nonce = wp_create_nonce('sfc_resume_comparison_tool_nonce');

    // Pass data to script only on the needed pages
    if (current_user_can('manage_options')) {
        global $post;
        if (has_shortcode($post->post_content, 'sfc_resume_comparison_tool_shortcode')) {
            wp_localize_script('sfc_handle_results_js', 'sfcResumeComparisonToolAjax', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => $ajax_nonce,
                'maxFileSize' => wp_max_upload_size()
            ));
        }
    }
}

/**
 * Handle comparison tool form submission via Ajax and return results as HTML.
 */
function sfc_handle_comparison_ajax()
{
    // Check if request is valid and nonce is valid
    if (!check_ajax_referer('sfc_resume_comparison_tool_nonce', 'security', false)) {
        $error_message = new WP_Error('security_error', __('Security check failed. Please refresh the page and try again.'));
        wp_send_json_error($error_message->get_error_message());
    }

    $resume_file = $_FILES['resume_file'];
    $job_description_file = $_FILES['job_description_file'];

    // Handle uploaded files and calculate word match percentage
    $word_match_percentage = sfc_handle_uploaded_files($resume_file, $job_description_file);

    // Check if word match percentage calculation was successful
    if (is_wp_error($word_match_percentage)) {
        $error_message = new WP_Error('word_match_percentage_error', $word_match_percentage->get_error_message());
        wp_send_json_error($error_message->get_error_message());
    }

    // Send results
    wp_send_json_success($word_match_percentage);

    // Make sure ajax handler dies when finished
    wp_die();
}

// Enqueue scripts
add_action('wp_enqueue_scripts', 'sfc_resume_comparison_tool_enqueue_scripts');

// Register shortcode to display form
add_shortcode('sfc_resume_comparison_tool_shortcode', 'sfc_resume_comparison_tool_shortcode');

// Register Ajax action for comparison tool form submission
add_action('wp_ajax_sfc_handle_comparison_ajax', 'sfc_handle_comparison_ajax');
add_action('wp_ajax_nopriv_sfc_handle_comparison_ajax', 'sfc_handle_comparison_ajax');


/**
 * Handle uploaded files by sanitizing file names, uploading files, comparing them, and deleting them.
 *
 * @return float|WP_Error The percentage of word match between the uploaded files or WP_Error object on failure.
 */
function sfc_handle_uploaded_files($resume_file, $job_description_file)
{

    // Check if both files were uploaded successfully
    if ($resume_file['error'] !== UPLOAD_ERR_OK || $job_description_file['error'] !== UPLOAD_ERR_OK) {
        return new WP_Error('file_upload_error', 'Both resume and job description files must be uploaded successfully.');
    }

    // Sanitize and make file names unique
    $resume_file['name'] = wp_unique_filename(wp_upload_dir()['path'], sanitize_file_name($resume_file['name']));
    $job_description_file['name'] = wp_unique_filename(wp_upload_dir()['path'], sanitize_file_name($job_description_file['name']));

    // Upload files
    $uploaded_resume_ID = media_handle_upload('resume_file', 0);
    $uploaded_job_description_ID = media_handle_upload('job_description_file', 0);

    // Check for errors in file upload
    if (is_wp_error($uploaded_resume_ID)) {
        return new WP_Error('file_upload_error', 'An error occurred while uploading the resume file.');
    }
    if (is_wp_error($uploaded_job_description_ID)) {
        return new WP_Error('file_upload_error', 'An error occurred while uploading the job description file.');
    }

    // Compare files and get percentage match
    $word_match_percentage = sfc_compare_resume_and_job_description($uploaded_resume_ID, $uploaded_job_description_ID);

    // Delete uploaded files
    wp_delete_attachment($uploaded_resume_ID, true);
    wp_delete_attachment($uploaded_job_description_ID, true);

    // Return word match percentage
    return $word_match_percentage;
}


/**
 * Compares a resume and a job description file and calculates the word match percentage.
 *
 * @param WP_Post $resume_attachment The resume attachment post object to compare.
 * @param WP_Post $job_description_attachment The job description attachment post object to compare.
 * @return string The word match percentage or an error message.
 */
function sfc_compare_resume_and_job_description($uploaded_resume_ID, $uploaded_job_description_ID)
{

    // Get the absolute path to the file
    $resume_file_path = get_attached_file($uploaded_resume_ID);
    $job_description_file_path = get_attached_file($uploaded_job_description_ID);

    // Check if resume and job description files exist
    if (!is_file($resume_file_path)) {
        return new WP_Error('resume_file_not_found', __('Error: Resume file not found.', 'sfc_resume_comparison_tool'));
    }
    if (!is_file($job_description_file_path)) {
        return new WP_Error('job_description_file_not_found', __('Error: Job description file not found.', 'sfc_resume_comparison_tool'));
    }

    // Get contents of resume and job description files
    $resume_contents = sfc_handle_content_extraction($resume_file_path);
    $job_description_contents = sfc_handle_content_extraction($job_description_file_path);

    // Check for errors in getting contents
    if (is_wp_error($resume_contents)) {
        return new WP_Error('file_contents_empty_string', __('Error occurred. Resume file appears empty.', 'sfc_resume_comparison_tool'));
    }
    if (is_wp_error($job_description_contents)) {
        return new WP_Error('file_contents_empty_string', __('Error occurred. Job description file appears empty.', 'sfc_resume_comparison_tool'));
    }

    // Process file contents 
    $resume_words = sfc_process_files_for_comparison($resume_contents);
    $job_description_words = sfc_process_files_for_comparison($job_description_contents);

    // Calculate word match percentage between arrays of unique words
    $match_percentage = sfc_calculate_word_match_percentage($resume_words, $job_description_words);

    // Return word match percentage
    return $match_percentage;
}

/**
 * Handle content extraction based on file type.
 *
 * @param string $file_path File path to extract content from.
 *
 * @return string|WP_Error Extracted content or WP_Error on failure.
 */
function sfc_handle_content_extraction($file_path)
{
    // Determine file type.
    $file_type = pathinfo($file_path, PATHINFO_EXTENSION);

    // Extract content based on file type.
    switch ($file_type) {
        case 'txt':
            $content = sfc_extract_txt_content($file_path);
            break;
        case 'doc':
        case 'docx':
            $content = sfc_extract_doc_content($file_path);
            break;
        case 'pdf':
            $content = sfc_extract_pdf_content($file_path);
            break;
        default:
            return new WP_Error('invalid_file_type', 'Invalid file type.');
    }

    // Verify $file_contents is a non-empty string
    if (empty(trim($content))) {
        return new WP_Error('file_contents_empty_string', __('Error occurred. File appears empty.', 'sfc_resume_comparison_tool'));
    }

    // Return extracted content.
    return $content;
}

/**
 * Extracts text content from a .txt file
 *
 * @param string $file_path The path to the .txt file
 *
 * @return string The contents of the .txt file as a string
 */
function sfc_extract_txt_content($file_path)
{
    $content = file_get_contents($file_path);
    return $content;
}

/**
 * Extracts text content from a .doc or .docx file
 *
 * @param string $file_path The path to the .doc or .docx file
 *
 * @return string The contents of the .doc or .docx file as a string
 */
function sfc_extract_doc_content($file_path)
{
    $php_word = \PhpOffice\PhpWord\IOFactory::load($file_path);
    $sections = $php_word->getSections();

    $text = '';
    foreach ($sections as $section) {
        $elements = $section->getElements();
        foreach ($elements as $element) {
            // If the element is a table, loop through its rows and cells to find any text elements and add them to the $text variable
            if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                $rows = $element->getRows();
                foreach ($rows as $row) {
                    $cells = $row->getCells();
                    foreach ($cells as $cell) {
                        $cell_elements = $cell->getElements();
                        foreach ($cell_elements as $cell_element) {
                            if ($cell_element instanceof \PhpOffice\PhpWord\Element\Text) {
                                $text .= $cell_element->getText();
                            }
                        }
                    }
                }
            }
            // If the element is a text run, loop through its elements to find any text elements and add them to the $text variable
            elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                $text_elements = $element->getElements();
                foreach ($text_elements as $text_element) {
                    if ($text_element instanceof \PhpOffice\PhpWord\Element\Text) {
                        $text .= $text_element->getText();
                    }
                }
            }
            // If the element is a list item or text element, add its text content to the $text variable
            elseif ($element instanceof \PhpOffice\PhpWord\Element\ListItem || $element instanceof \PhpOffice\PhpWord\Element\Text) {
                $text .= $element->getText();
            }
        }
    }
    return $text;
}

/**
 * Extracts text content from a .pdf file
 *
 * @param string $file_path The path to the .pdf file
 *
 * @return string The contents of the .pdf file as a string
 */
function sfc_extract_pdf_content($file_path)
{
    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($file_path);
    $pages = $pdf->getPages();
    $page_texts = array();
    foreach ($pages as $page) {
        $page_texts[] = $page->getText();
    }
    $contents = implode("\n", $page_texts);
    return $contents;
}

/**
 * Processes the contents of a file for comparison by cleaning and checking for malicious code. It returns an array of unique word tokens.
 *
 * @param string $file_contents The contents of the file to sanitize.
 * @return array An array of unique word tokens in the file contents.
 * @throws Exception When the given file contents are not a string or when it contains malicious code.
 */
function sfc_process_files_for_comparison($file_contents)
{
    if (!is_string($file_contents)) {
        $file_contents = "";
    }

    // Sanitize file contents
    $file_contents = wp_strip_all_tags($file_contents);

    // Make text all lowercase
    $file_contents = strtolower($file_contents);

    // Remove special characters, numbers, and extra whitespace from the string
    $file_contents = preg_replace('/[^a-z\s]+/', '', $file_contents);
    $file_contents = preg_replace('/\s+/', ' ', $file_contents);

    // Split contents into array of unique word tokens
    $unique_words = array_unique(explode(' ', $file_contents));

    // Remove stopwords from unique words 
    $unique_words = sfc_strip_stopwords($unique_words);

    // Sort words
    sort($unique_words);

    return $unique_words;
}

/**
 * Strips stop words from an array of word tokens.
 *
 * @param array $words The array of word tokens to strip stop words from.
 * @return array The array of word tokens without stop words.
 */
function sfc_strip_stopwords($words)
{
    $stopwords = array(
        'a', 'about', 'above', 'after', 'again', 'against', 'all', 'am', 'an', 'and', 'any', 'are', 'aren\'t',
        'as', 'at', 'be', 'because', 'been', 'before', 'being', 'below', 'between', 'both', 'but', 'by', 'can\'t',
        'cannot', 'could', 'couldn\'t', 'did', 'didn\'t', 'do', 'does', 'doesn\'t', 'doing', 'don\'t', 'down',
        'during', 'each', 'few', 'for', 'from', 'further', 'had', 'hadn\'t', 'has', 'hasn\'t', 'have', 'haven\'t',
        'having', 'he', 'he\'d', 'he\'ll', 'he\'s', 'her', 'here', 'here\'s', 'hers', 'herself', 'him', 'himself',
        'his', 'how', 'how\'s', 'i', 'i\'d', 'i\'ll', 'i\'m', 'i\'ve', 'if', 'in', 'into', 'is', 'isn\'t', 'it',
        'it\'s', 'its', 'itself', 'let\'s', 'me', 'more', 'most', 'mustn\'t', 'my', 'myself', 'no', 'nor', 'not',
        'of', 'off', 'on', 'once', 'only', 'or', 'other', 'ought', 'our', 'ours', 'ourselves', 'out', 'over',
        'own', 'same', 'shan\'t', 'she', 'she\'d', 'she\'ll', 'she\'s', 'should', 'shouldn\'t', 'so', 'some',
        'such', 'than', 'that', 'that\'s', 'the', 'their', 'theirs', 'them', 'themselves', 'then', 'there',
        'there\'s', 'these', 'they', 'they\'d', 'they\'ll', 'they\'re', 'they\'ve', 'this', 'those', 'through',
        'to', 'too', 'under', 'until', 'up', 'very', 'was', 'wasn\'t', 'we', 'we\'d', 'we\'ll', 'we\'re',
        'we\'ve', 'were', 'weren\'t', 'what', 'what\'s', 'when', 'when\'s', 'where', 'where\'s', 'which', 'while',
        'who', 'who\'s', 'whom', 'why', 'why\'s', 'with', 'won\'t', 'would', 'wouldn\'t', 'you', 'you\'d', 'you\'ll',
        'you\'re', 'you\'ve', 'your', 'yours', 'yourself', 'yourselves'
    );
    $words = array_diff($words, $stopwords);
    return $words;
}

/**
 * Calculates the word match percentage between two arrays of unique words.
 *
 * @param array $words1 The first array of unique words.
 * @param array $words2 The second array of unique words.
 * @return float|WP_Error The word match percentage or WP_Error if $words2 is empty.
 */
function sfc_calculate_word_match_percentage($words1, $words2)
{
    if (empty($words2)) {
        return new WP_Error('empty_words2', 'Cannot run comparison because file(s) is empty.');
    }
    $common_words = array_intersect($words1, $words2);
    $match_percentage = (count($common_words) / count($words2)) * 100;
    return round($match_percentage);
}









