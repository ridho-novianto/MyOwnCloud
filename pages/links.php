<?php
/**
 * Link Manager Page
 */
$pageTitle = 'Link Manager';
$pageIcon = 'link';
$headerSearch = true;
$searchPlaceholder = 'Cari link...';
$headerButton = ['text' => 'Tambah Link', 'onclick' => 'openLinkModal()', 'id' => 'addLinkBtn'];
$pageScripts = ['links.js'];
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$uid = currentUserId();

// Get categories
$catStmt = $db->prepare('SELECT DISTINCT category, COUNT(*) as cnt FROM links WHERE user_id = ? GROUP BY category ORDER BY category');
$catStmt->execute([$uid]);
$categories = $catStmt->fetchAll();

$totalLinks = $db->prepare('SELECT COUNT(*) FROM links WHERE user_id = ?');
$totalLinks->execute([$uid]);
$total = $totalLinks->fetchColumn();

// Get all links
$linkStmt = $db->prepare('SELECT * FROM links WHERE user_id = ? ORDER BY is_pinned DESC, created_at DESC');
$linkStmt->execute([$uid]);
$links = $linkStmt->fetchAll();
?>

<!-- Category Filter -->
<div class="filter-tabs">
    <button class="filter-tab active" data-filter="all"><i class="fas fa-globe"></i> Semua (<?= $total ?>)</button>
    <?php foreach ($categories as $cat): ?>
    <button class="filter-tab" data-filter="<?= sanitize($cat['category']) ?>"><?= sanitize($cat['category']) ?> (<?= $cat['cnt'] ?>)</button>
    <?php endforeach; ?>
</div>

<!-- Links Grid -->
<div class="links-grid" id="linksGrid">
    <?php if (empty($links)): ?>
        <div class="empty-state">
            <i class="fas fa-link"></i>
            <h3>Belum ada link</h3>
            <p>Klik "Tambah Link" untuk menyimpan link penting</p>
        </div>
    <?php else: ?>
        <?php foreach ($links as $link): ?>
        <div class="link-card" data-id="<?= $link['id'] ?>" data-category="<?= sanitize($link['category']) ?>" style="border-top: 3px solid <?= sanitize($link['icon_color']) ?>">
            <div class="link-card-header">
                <div class="link-icon" style="background: <?= sanitize($link['icon_color']) ?>20; color: <?= sanitize($link['icon_color']) ?>">
                    <i class="fas fa-bookmark"></i>
                </div>
                <div class="link-info">
                    <h4><?= sanitize($link['title']) ?></h4>
                    <a href="<?= sanitize($link['url']) ?>" target="_blank" class="link-url" onclick="trackClick(<?= $link['id'] ?>)"><?= sanitize($link['url']) ?></a>
                </div>
            </div>
            <?php if ($link['description']): ?>
            <p class="link-desc"><?= sanitize($link['description']) ?></p>
            <?php endif; ?>
            <div class="link-card-footer">
                <div class="link-tags">
                    <?php if ($link['is_pinned']): ?>
                        <span class="badge badge-pinned"><i class="fas fa-thumbtack"></i> Pinned</span>
                    <?php endif; ?>
                    <span class="click-count"><i class="fas fa-mouse-pointer"></i> <?= $link['click_count'] ?></span>
                </div>
                <div class="link-actions">
                    <button class="action-btn" onclick="togglePin(<?= $link['id'] ?>, <?= $link['is_pinned'] ?>)" title="<?= $link['is_pinned'] ? 'Unpin' : 'Pin' ?>">
                        <i class="fas fa-thumbtack"></i>
                    </button>
                    <button class="action-btn" onclick="editLink(<?= $link['id'] ?>)" title="Edit">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button class="action-btn danger" onclick="deleteLink(<?= $link['id'] ?>)" title="Hapus">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Link Modal -->
<div class="modal" id="linkModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="linkModalTitle">Tambah Link</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="linkForm" onsubmit="saveLink(event)">
            <input type="hidden" id="linkId" value="">
            <div class="form-group">
                <label>Judul</label>
                <input type="text" id="linkTitle" required placeholder="Nama link..." maxlength="255">
            </div>
            <div class="form-group">
                <label>URL</label>
                <input type="url" id="linkUrl" required placeholder="https://example.com">
            </div>
            <div class="form-group">
                <label>Deskripsi</label>
                <input type="text" id="linkDesc" placeholder="Deskripsi singkat (opsional)..." maxlength="500">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Kategori</label>
                    <input type="text" id="linkCategory" placeholder="Kategori" list="categoryList" value="Uncategorized">
                    <datalist id="categoryList">
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= sanitize($cat['category']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label>Warna</label>
                    <input type="color" id="linkColor" value="#00e5ff" class="color-input">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
