<?php
/**
 * Plugin Name: Magic Moon Tools
 * Description: Internal deployment tools for Magic Moon Studio.
 * Version: 1.0
 * Author: Magic Moon Studio
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Run any queued task files dropped into this folder
$task_file = __DIR__ . '/run-task.php';
if (file_exists($task_file)) {
    include_once $task_file;
}
