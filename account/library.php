<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_login('login.php');
$user = current_user();
$stored = find_user_by_id((string)$user['id']) ?? $user;
$query = strtolower(trim((string)($_GET['q'] ?? '')));
$type = strtolower(trim((string)($_GET['type'] ?? '')));
$resources = array_values(array_filter(read_json_array(RESOURCES_FILE), static function (array $resource) use ($stored, $query, $type): bool {
    if (($resource['status'] ?? 'published') !== 'published' || !user_can_access_resource($stored, $resource)) return false;
    if ($type !== '' && strtolower((string)($resource['contentType'] ?? '')) !== $type) return false;
    if ($query !== '') {
        $haystack = strtolower(implode(' ', [
            (string)($resource['title'] ?? ''),
            (string)($resource['subtitle'] ?? ''),
            (string)($resource['description'] ?? ''),
            implode(' ', is_array($resource['tags'] ?? null) ? $resource['tags'] : []),
        ]));
        if (!str_contains($haystack, $query)) return false;
    }
    return true;
}));
usort($resources, static fn(array $a, array $b): int => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
$types = array_values(array_unique(array_filter(array_map(static fn(array $resource): string => strtolower((string)($resource['contentType'] ?? '')), read_json_array(RESOURCES_FILE)))));
sort($types);
$accessLabels = ['listener' => 'All members', 'velvet-patron' => 'Velvet Patron', 'inner-circle' => 'Inner Circle', 'staff' => 'Staff'];
account_header('Member Library', 'library');
?>
<section class="member-page-head"><div class="wrap"><p class="eyebrow">Private member library</p><h1>Your books, guides, games, and resources.</h1><p>Access is based on your membership, individual purchases, and permissions assigned to your account.</p></div></section>
<section class="section"><div class="wrap">
  <form class="library-toolbar" method="get"><label><span class="sr-only">Search library</span><input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search your library"></label><label><span class="sr-only">Resource type</span><select name="type"><option value="">All types</option><?php foreach ($types as $option): ?><option value="<?= e($option) ?>"<?= $type === $option ? ' selected' : '' ?>><?= e(ucwords($option)) ?></option><?php endforeach; ?></select></label><button class="button button--ghost" type="submit">Search</button><a class="text-link" href="library.php">Clear</a><span class="library-count"><?= count($resources) ?> resource<?= count($resources) === 1 ? '' : 's' ?></span></form>
  <?php if ($resources): ?><div class="library-grid"><?php foreach ($resources as $resource): ?>
    <article class="library-card" id="<?= e($resource['id'] ?? '') ?>">
      <div class="library-card__top"><span class="content-type"><?= e($resource['contentType'] ?? 'Resource') ?></span><span class="access-pill"><?= e($accessLabels[$resource['accessLevel'] ?? 'listener'] ?? ($resource['accessLevel'] ?? 'Member access')) ?></span></div>
      <div class="library-card__art"><span><?= e(strtoupper(substr((string)($resource['contentType'] ?? 'R'), 0, 1))) ?></span><i><?= e(strtoupper((string)($resource['format'] ?? 'FILE'))) ?></i></div>
      <div class="library-card__content"><p class="eyebrow"><?= e($resource['subtitle'] ?? 'Member resource') ?></p><h2><?= e($resource['title'] ?? 'Resource') ?></h2><p><?= e($resource['description'] ?? '') ?></p><?php if (!empty($resource['tags'])): ?><div class="tag-row"><?php foreach ((array)$resource['tags'] as $tag): ?><span><?= e($tag) ?></span><?php endforeach; ?></div><?php endif; ?></div>
      <div class="library-card__actions"><a class="button button--primary" href="download.php?id=<?= rawurlencode((string)($resource['id'] ?? '')) ?>">Open resource</a></div>
    </article>
  <?php endforeach; ?></div><?php else: ?><div class="empty-state empty-state--wide"><strong>No resources match this view.</strong><p>Resources appear after they are published and connected to your membership or purchase access.</p></div><?php endif; ?>
</div></section>
<?php account_footer(); ?>
