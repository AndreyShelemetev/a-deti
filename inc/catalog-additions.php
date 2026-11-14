<?php
/** Добавочные программы со старого сайта. Старые записи сохраняем; исправляем только неизменённый SEO собственного импорта v1. */
declare(strict_types=1);

function at_catalog_additions(PDO $pdo, string $file): array
{
    $marker = 'content.moscow-winter-2027.v2';
    $saved = $pdo->prepare('SELECT v FROM atour_settings WHERE k = ?');
    $saved->execute([$marker]);
    if ($saved->fetchColumn() !== false || !is_file($file)) return [];
    $seed = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($seed) || count($seed['tours'] ?? []) !== 10) throw new RuntimeException('Не читается дополнение зимнего каталога.');
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    if ($mysql && (int)$pdo->query("SELECT GET_LOCK('atour_winter_2027', 5)")->fetchColumn() !== 1) return [];
    try {
        $pdo->beginTransaction();
        $saved->execute([$marker]);
        if ($saved->fetchColumn() !== false) { $pdo->commit(); return []; }
        $saved->execute(['content.moscow-winter-2027.v1']);
        $legacyValue = $saved->fetchColumn();
        $legacy = $legacyValue === false ? [] : json_decode((string)$legacyValue,true,512,JSON_THROW_ON_ERROR);
        $legacySlugs = array_values(array_intersect(array_column($seed['tours'],'slug'),(array)($legacy['tours'] ?? [])));
        // FOR UPDATE защищает и от создания одноимённой записи во время переноса.
        $filterMissing = static function (array $items, string $table, string $column) use ($pdo,$mysql): array {
            $rows = $pdo->query('SELECT ' . $column . ' FROM ' . $table . ($mysql ? ' FOR UPDATE' : ''))->fetchAll(PDO::FETCH_COLUMN);
            return array_values(array_filter($items, static fn(array $item): bool => !in_array($item[$column],$rows,true)));
        };
        $partial = [
            'tours' => $filterMissing($seed['tours'],'tours','slug'),
            'regions' => $filterMissing($seed['regions'],'regions','slug'),
            'tags' => $filterMissing($seed['tags'],'tags','slug'),
            'catalogPages' => $filterMissing($seed['catalogPages'],'catalog_pages','path'),
        ];
        require_once __DIR__ . '/seed.php';
        $counts = at_seed_apply($pdo,$partial);
        $counts['seo_titles'] = 0;
        $seo = $pdo->prepare('SELECT seo_title FROM tours WHERE slug = ?' . ($mysql ? ' FOR UPDATE' : ''));
        $updateSeo = $pdo->prepare('UPDATE tours SET seo_title = ? WHERE slug = ?');
        foreach ($seed['tours'] as $tour) {
            if (!in_array($tour['slug'],$legacySlugs,true) || !isset($tour['legacySeoTitle'])) continue;
            $seo->execute([$tour['slug']]);
            // Сравниваем байты в PHP: SQL collation могла бы пропустить правку регистра владельцем.
            if ($seo->fetchColumn() !== $tour['legacySeoTitle']) continue;
            $updateSeo->execute([$tour['seoTitle'],$tour['slug']]);
            $counts['seo_titles']++;
        }
        $managedSlugs = array_values(array_unique(array_merge($legacySlugs,array_column($partial['tours'],'slug'))));
        $pdo->prepare('INSERT INTO atour_settings (k,v) VALUES (?,?)')->execute([$marker,json_encode(['tours'=>$managedSlugs,'added_at'=>gmdate(DATE_ATOM)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        $pdo->commit();
        return $counts;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    } finally {
        if ($mysql) $pdo->query("SELECT RELEASE_LOCK('atour_winter_2027')");
    }
}
