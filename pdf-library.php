<?php
/*
Plugin Name: PDF Library (s2member Restricted)
Description: Displays a readonly PDF library restricted by s2member membership levels.
Version: 1.0
Author: AI Assistant
*/

if (!defined('ABSPATH')) exit;

/**
 * Configuration for PDF Library
 * This can be moved to an admin options page later.
 */
function get_pdf_library_config() {
    return [
        'levels' => [
            'basic' => [
                'label' => 'Basic Access',
                'categories' => [
                    'Getting Started' => [
                        'files' => [
                            ['title' => 'Introduction', 'url' => 'file_1.pdf', 'cat' => 'Getting Started'],
                            ['title' => 'Quick Start Guide', 'url' => 'file_2.pdf', 'cat' => 'Getting Started'],
                        ]
                    ],
                ]
            ],
            'premium' => [
                'label' => 'Premium Access',
                'categories' => [
                    'Advanced Tutorials' => [
                        'files' => [
                            ['title' => 'Deep Dive', 'url' => 'file_3.pdf', 'cat' => 'Advanced Tutorials'],
                        ]
                    ],
                    'Getting Started' => [
                        'files' => [
                            ['title' => 'Introduction', 'url' => 'file_1.pdf', 'cat' => 'Getting Started'],
                            ['title' => 'Quick Start Guide', 'url' => 'file_2.pdf', 'cat' => 'Getting Started'],
                        ]
                    ],
                ]
            ],
        ]
    ];
}

/**
 * Register the PDF Library shortcode.
 * Usage: [pdf_library]
 */
add_shortcode('pdf_library', 'render_pdf_library');

function render_pdf_library() {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return '<p>Please log in to view the library.</p>';
    }

    // Mocking s2member check
    // In a real environment, you would use s2member functions or check user meta.
    // Example: $user_level = s2member_get_user_membership_level($user_id);
    $user_level = 'premium'; // Mocked for now

    $config = get_pdf_library_config();
    
    if (!isset($config['levels'][$user_level])) {
        return '<p>Access denied or level not found.</p>';
    }

    $current_level_data = $config['levels'][$user_level];
    $output = '<div class="pdf-library-container" style="border:1px solid #ccc; padding:20px; max-width:800px; margin:20px auto;">';
    $output .= '<h2>' . $current_level_data['label'] . ' Library</h2>';
    $output .= '<p>Welcome! You have access to the following categories:</p>';

    foreach ($current_level_data['categories'] as $cat_name => $cat_data) {
        $output .= '<div class="pdf-category" style="margin-bottom:20px;">';
        $output .= '<h3>' . $cat_name . '</h3>';
        $output .= '<ul>';
        foreach ($cat_data['files'] as $file) {
            $output .= '<li><a href="' . esc_url($file['url']) . '" target="_blank">' . esc_html($file['title']) . '</a></li>';
        }
        $output .= '</ul>';
        $output .= '</div>';
    }

    $output .= '</div>';

    // Note: To prevent downloads, we would ideally use a custom viewer or 
    // a JS library like PDF.js. Simple links allow download/view in browser.
    
    return $output;
}

