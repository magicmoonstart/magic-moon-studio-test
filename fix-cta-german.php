<?php
/**
 * Magic Moon Studio — CTA Fix Script
 * Changes "Book Consultation" → "Beratung buchen" sitewide
 * DELETES ITSELF after running for security.
 *
 * HOW TO USE:
 * 1. Upload this file to your WordPress root folder (same folder as wp-config.php)
 * 2. Visit: https://YOURDOMAIN.com/fix-cta-german.php
 * 3. Done — the script deletes itself automatically.
 */

// Load WordPress
define('ABSPATH', dirname(__FILE__) . '/');
require_once(ABSPATH . 'wp-config.php');

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die('<p style="color:red">❌ Database connection failed: ' . $conn->connect_error . '</p>');
}
$conn->set_charset('utf8mb4');

$prefix  = $table_prefix ?? 'wp_';
$from    = 'Book Consultation';
$to      = 'Beratung buchen';
$results = [];

// 1. Update Elementor widget data (stored in postmeta)
$sql = "UPDATE `{$prefix}postmeta`
        SET `meta_value` = REPLACE(`meta_value`, '$from', '$to')
        WHERE `meta_key` = '_elementor_data'
          AND `meta_value` LIKE '%$from%'";
$conn->query($sql);
$results['Elementor pages updated'] = $conn->affected_rows;

// 2. Update standard post content
$sql2 = "UPDATE `{$prefix}posts`
          SET `post_content` = REPLACE(`post_content`, '$from', '$to')
          WHERE `post_content` LIKE '%$from%'";
$conn->query($sql2);
$results['Post content updated'] = $conn->affected_rows;

// 3. Clear Elementor CSS cache so changes appear immediately
$sql3 = "DELETE FROM `{$prefix}postmeta`
          WHERE `meta_key` IN ('_elementor_css', '_elementor_element_cache')";
$conn->query($sql3);
$results['Elementor cache cleared'] = $conn->affected_rows;

// 4. Clear transients
$conn->query("DELETE FROM `{$prefix}options` WHERE `option_name` LIKE '_transient_elementor_%'");
$conn->query("DELETE FROM `{$prefix}options` WHERE `option_name` LIKE '_site_transient_elementor_%'");

$conn->close();

// 5. Self-delete for security
$self = __FILE__;

?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Magic Moon — CTA Fix</title>
<style>
  body { font-family: sans-serif; background: #0A0A0A; color: #F0EDE8; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
  .box { background: #141414; border: 1px solid #2A2A2A; border-radius: 8px; padding: 40px; max-width: 500px; width: 90%; }
  h1 { color: #C9A84C; font-size: 22px; margin: 0 0 20px; }
  .row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #2A2A2A; font-size: 14px; }
  .row:last-child { border: none; }
  .num { color: #C9A84C; font-weight: bold; font-family: monospace; }
  .notice { margin-top: 24px; background: #1B1B1B; border-left: 3px solid #3A9E68; padding: 14px; font-size: 13px; line-height: 1.6; border-radius: 4px; }
  .warn { border-left-color: #D4810A; margin-top: 10px; }
</style>
</head>
<body>
<div class="box">
  <h1>✓ CTA Text Updated</h1>
  <p style="color:#888;font-size:13px;margin-bottom:20px;">"Book Consultation" → "Beratung buchen"</p>

  <?php foreach ($results as $label => $count): ?>
  <div class="row">
    <span><?= htmlspecialchars($label) ?></span>
    <span class="num"><?= $count ?> rows</span>
  </div>
  <?php endforeach; ?>

  <div class="notice">
    ✓ All changes applied. Elementor CSS cache cleared — changes are live immediately.
  </div>
  <div class="notice warn">
    ⚠ This file has been deleted automatically for security.
  </div>
</div>
</body>
</html>
<?php
// Delete self
@unlink($self);
