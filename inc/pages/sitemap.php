<?php
/** /sitemap.xml — карта сайта из базы. */
declare(strict_types=1);

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach (at_sitemap() as $u) {
    echo '<url><loc>' . at_e(at_abs($u['loc'])) . '</loc><lastmod>' . at_e($u['lastmod']) . '</lastmod><changefreq>' . $u['changefreq'] . '</changefreq><priority>' . $u['priority'] . '</priority></url>' . "\n";
}
echo '</urlset>';
