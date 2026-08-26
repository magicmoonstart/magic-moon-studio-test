-- ============================================================
-- Magic Moon Studio — CTA Text Fix
-- Change "Book Consultation" → "Beratung buchen" (German)
-- on all German pages across the site.
--
-- HOW TO RUN:
-- 1. Open phpMyAdmin on your hosting (Hostinger panel → Databases)
-- 2. Select your WordPress database
-- 3. Click "SQL" tab
-- 4. Paste this script and click "Go"
--
-- NOTE: Replace "wp_" with your actual table prefix if different.
-- ============================================================

-- Update Elementor page builder content (main storage)
UPDATE `wp_postmeta`
SET `meta_value` = REPLACE(`meta_value`, 'Book Consultation', 'Beratung buchen')
WHERE `meta_key` = '_elementor_data'
  AND `meta_value` LIKE '%Book Consultation%';

-- Update standard WordPress post content (fallback)
UPDATE `wp_posts`
SET `post_content` = REPLACE(`post_content`, 'Book Consultation', 'Beratung buchen')
WHERE `post_content` LIKE '%Book Consultation%';

-- Update Elementor page builder content (CSS cache — forces regeneration)
DELETE FROM `wp_postmeta`
WHERE `meta_key` IN ('_elementor_css', '_elementor_controls_usage')
  AND `post_id` IN (
    SELECT `post_id` FROM (
      SELECT `post_id` FROM `wp_postmeta`
      WHERE `meta_key` = '_elementor_data'
        AND `meta_value` LIKE '%Beratung buchen%'
    ) AS sub
  );

-- Verify: show how many rows were updated
SELECT COUNT(*) AS pages_updated
FROM `wp_postmeta`
WHERE `meta_key` = '_elementor_data'
  AND `meta_value` LIKE '%Beratung buchen%';
