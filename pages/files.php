<?php
/**
 * File Manager Page
 */
$pageTitle = 'File Manager';
$pageIcon = 'folder-open';
$headerSearch = true;
$searchPlaceholder = 'Cari file...';
$headerButton = ['text' => 'Upload', 'onclick' => 'document.getElementById("fileInput").click()', 'id' => 'uploadBtn'];
$pageScripts = ['files.js'];
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$uid = currentUserId();
$storage = getUserStorageInfo($uid);
$folderId = isset($_GET['folder']) ? (int)$_GET['folder'] : null;

// Get breadcrumb
$breadcrumb = [];
if ($folderId) {
    $tmpId = $folderId;
    while ($tmpId) {
        $stmt = $db->prepare('SELECT id, name, parent_id FROM folders WHERE id = ? AND user_id = ?');
        $stmt->execute([$tmpId, $uid]);
        $folder = $stmt->fetch();
        if ($folder) {
            array_unshift($breadcrumb, $folder);
            $tmpId = $folder['parent_id'];
        } else break;
    }
}

// Get folders
$folderStmt = $db->prepare('SELECT * FROM folders WHERE user_id = ? AND parent_id ' . ($folderId ? '= ?' : 'IS NULL') . ' ORDER BY name');
$folderParams = [$uid];
if ($folderId) $folderParams[] = $folderId;
$folderStmt->execute($folderParams);
$folders = $folderStmt->fetchAll();

// Get files
$fileStmt = $db->prepare('SELECT * FROM files WHERE user_id = ? AND folder_id ' . ($folderId ? '= ?' : 'IS NULL') . ' ORDER BY created_at DESC');
$fileParams = [$uid];
if ($folderId) $fileParams[] = $folderId;
$fileStmt->execute($fileParams);
$files = $fileStmt->fetchAll();

$fileCount = count($files) + count($folders);
?>

<!-- Storage Bar -->
<div class="storage-bar-container">
    <?php if ($storage['is_admin'] ?? false): ?>
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
            <h3 style="margin:0;font-size:16px;color:var(--text-primary);"><i class="fas fa-database" style="color:var(--cyan);margin-right:8px;"></i>Storage</h3>
            <i class="fas fa-cog" style="color:var(--text-muted);cursor:pointer;" title="Storage Settings"></i>
        </div>
        <div style="display: flex; gap: 16px; align-items: center; margin-bottom: 15px;">
            <div style="font-size: 48px; color: var(--text-primary);"><i class="fas fa-hdd"></i></div>
            <div style="flex:1;">
                <span class="badge" style="border: 1px solid var(--green); color: var(--green); background: transparent; padding: 2px 8px; margin-bottom: 6px; display: inline-block;">Healthy</span>
                <div style="font-size: 14px; font-weight: 500; color: var(--text-primary);">Used: <?= formatBytes($storage['storage_used']) ?></div>
                <div style="font-size: 13px; color: var(--text-muted);">Total: <?= formatBytes($storage['storage_quota']) ?></div>
            </div>
        </div>
        <div class="storage-bar">
            <div class="storage-fill" style="width: <?= $storage['storage_quota'] > 0 ? min(100, ($storage['storage_used'] / $storage['storage_quota']) * 100) : 0 ?>%; background: linear-gradient(90deg, #448aff, #00e5ff);"></div>
        </div>
    <?php else: ?>
        <div class="storage-info">
            <i class="fas fa-database"></i> <strong>Storage</strong>
            <span class="storage-detail"><?= formatBytes($storage['storage_used']) ?> digunakan</span>
        </div>
        <div class="storage-bar">
            <div class="storage-fill" style="width: <?= $storage['storage_quota'] > 0 ? min(100, ($storage['storage_used'] / $storage['storage_quota']) * 100) : 0 ?>%"></div>
        </div>
        <div class="storage-meta">
            <span><?= $fileCount ?> file</span>
            <span><?= formatBytes($storage['storage_quota']) ?></span>
        </div>
    <?php endif; ?>
</div>

<!-- Upload Zone -->
<div class="upload-zone" id="uploadZone">
    <div class="upload-zone-content">
        <i class="fas fa-cloud-upload-alt"></i>
        <p>Drag & drop file di sini, atau klik untuk upload</p>
        <small>Maks <?= formatBytes(MAX_UPLOAD_SIZE) ?> per file. Semua jenis file diizinkan.</small>
    </div>
    <input type="file" id="fileInput" multiple style="display:none" onchange="handleFileSelect(this.files)">
    <input type="file" id="folderInput" webkitdirectory directory multiple style="display:none" onchange="handleFileSelect(this.files)">
</div>

<!-- Upload Progress -->
<div class="upload-progress-container" id="uploadProgress" style="display:none">
    <div class="upload-progress-bar">
        <div class="upload-progress-fill" id="uploadProgressFill"></div>
    </div>
    <span id="uploadProgressText">Uploading...</span>
</div>

<!-- File Type Filters -->
<div class="filter-tabs file-filters">
    <button class="filter-tab active" data-filter="all"><i class="fas fa-layer-group"></i> Semua</button>
    <button class="filter-tab" data-filter="image"><i class="fas fa-image"></i> Gambar</button>
    <button class="filter-tab" data-filter="document"><i class="fas fa-file-alt"></i> Dokumen</button>
    <button class="filter-tab" data-filter="video"><i class="fas fa-film"></i> Video</button>
    <button class="filter-tab" data-filter="audio"><i class="fas fa-music"></i> Audio</button>
    <button class="filter-tab" data-filter="archive"><i class="fas fa-file-archive"></i> Archive</button>
</div>

<!-- Breadcrumb & Actions -->
<div class="file-toolbar">
    <div class="breadcrumb">
        <a href="?page=files" class="breadcrumb-item"><i class="fas fa-folder"></i> Semua Folder</a>
        <?php foreach ($breadcrumb as $bc): ?>
        <span class="breadcrumb-sep">/</span>
        <a href="?page=files&folder=<?= $bc['id'] ?>" class="breadcrumb-item"><?= sanitize($bc['name']) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="file-toolbar-actions">
        <button class="btn btn-sm btn-secondary" onclick="document.getElementById('folderInput').click()">
            <i class="fas fa-upload"></i> Folder
        </button>
        <button class="btn btn-sm btn-secondary" onclick="openFolderModal()">
            <i class="fas fa-folder-plus"></i> Baru
        </button>
        <div class="view-toggle">
            <button class="view-btn active" id="gridViewBtn" onclick="setView('grid')"><i class="fas fa-th"></i></button>
            <button class="view-btn" id="listViewBtn" onclick="setView('list')"><i class="fas fa-list"></i></button>
        </div>
    </div>
</div>

<!-- File List -->
<div class="file-grid" id="fileGrid">
    <?php if (empty($folders) && empty($files)): ?>
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <h3>Tidak ada file</h3>
            <p>Upload file untuk memulai</p>
        </div>
    <?php endif; ?>

    <!-- Back / Up Directory Drop Target -->
    <?php if ($folderId): ?>
    <div class="file-item folder-item up-directory" data-type="folder" data-itemtype="folder" data-id="<?= end($breadcrumb)['parent_id'] ?? '' ?>" ondrop="handleDropMove(event)" ondragover="handleDragOverFile(event)" ondragleave="handleDragLeaveFile(event)">
        <a href="?page=files<?= end($breadcrumb)['parent_id'] ? '&folder='.end($breadcrumb)['parent_id'] : '' ?>" class="file-item-link">
            <div class="file-icon folder-icon" style="background:transparent; color:var(--text-muted);"><i class="fas fa-level-up-alt"></i></div>
            <div class="file-info">
                <span class="file-name">... (Kembali)</span>
                <span class="file-meta">Pindahkan ke Luar/Naik</span>
            </div>
        </a>
    </div>
    <?php endif; ?>

    <?php foreach ($folders as $folder): ?>
    <div class="file-item folder-item" data-type="folder" data-itemtype="folder" data-id="<?= $folder['id'] ?>" draggable="true" ondragstart="handleDragStartFile(event)" ondrop="handleDropMove(event)" ondragover="handleDragOverFile(event)" ondragleave="handleDragLeaveFile(event)">
        <a href="?page=files&folder=<?= $folder['id'] ?>" class="file-item-link">
            <div class="file-icon folder-icon"><i class="fas fa-folder"></i></div>
            <div class="file-info">
                <span class="file-name"><?= sanitize($folder['name']) ?></span>
                <span class="file-meta">Folder</span>
            </div>
        </a>
        <div class="file-actions">
            <button class="action-btn" onclick="renameFolder(<?= $folder['id'] ?>, '<?= sanitize($folder['name']) ?>')" title="Rename">
                <i class="fas fa-pen"></i>
            </button>
            <button class="action-btn danger" onclick="deleteFolder(<?= $folder['id'] ?>)" title="Hapus">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
    <?php endforeach; ?>

    <?php foreach ($files as $file): ?>
    <div class="file-item" data-itemtype="file" data-type="<?= getFileColorClass($file['mime_type']) ?>" data-mime="<?= sanitize($file['mime_type']) ?>" data-id="<?= $file['id'] ?>" draggable="true" ondragstart="handleDragStartFile(event)">
        <div class="file-item-link" onclick="previewFile(<?= $file['id'] ?>, '<?= sanitize($file['original_name']) ?>', '<?= sanitize($file['mime_type']) ?>', '<?= sanitize($file['filename']) ?>')">
            <?php if (str_starts_with($file['mime_type'], 'image/')): ?>
            <div class="file-icon file-thumb">
                <img src="<?= APP_URL ?>/uploads/<?= $uid ?>/<?= sanitize($file['filename']) ?>" alt="" loading="lazy">
            </div>
            <?php else: ?>
            <div class="file-icon <?= getFileColorClass($file['mime_type']) ?>"><i class="fas <?= getFileIcon($file['mime_type']) ?>"></i></div>
            <?php endif; ?>
            <div class="file-info">
                <span class="file-name"><?= sanitize($file['original_name']) ?></span>
                <span class="file-meta"><?= formatBytes($file['filesize']) ?> &bull; <?= date('d M Y', strtotime($file['created_at'])) ?></span>
            </div>
        </div>
        <div class="file-actions">
            <?php if (str_starts_with($file['mime_type'], 'audio/') || str_starts_with($file['mime_type'], 'video/') || str_starts_with($file['mime_type'], 'image/') || in_array($file['mime_type'], ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'text/plain', 'text/csv'])): ?>
            <button class="action-btn" onclick="openConvertModal(<?= $file['id'] ?>, '<?= sanitize($file['mime_type']) ?>')" title="Konversi">
                <i class="fas fa-exchange-alt"></i>
            </button>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/?page=api/files&action=download&id=<?= $file['id'] ?>" class="action-btn" title="Download">
                <i class="fas fa-download"></i>
            </a>
            <button class="action-btn danger" onclick="deleteFile(<?= $file['id'] ?>)" title="Hapus">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- File Preview Modal -->
<div class="modal" id="previewModal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3 id="previewTitle">Preview</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="preview-body" id="previewBody"></div>
    </div>
</div>

<!-- Folder Modal -->
<div class="modal" id="folderModal">
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h3>Buat Folder</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="folderForm" onsubmit="createFolder(event)">
            <div class="form-group">
                <label>Nama Folder</label>
                <input type="text" id="folderName" required placeholder="Nama folder...">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Buat</button>
            </div>
        </form>
    </div>
</div>

<!-- Convert Modal -->
<div class="modal" id="convertModal">
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h3><i class="fas fa-exchange-alt" style="color:var(--cyan);margin-right:6px"></i> Konversi File</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="convertForm" onsubmit="handleConvert(event)">
            <input type="hidden" id="convertFileId">
            <input type="hidden" id="convertFileMime">
            <div class="form-group">
                <label><i class="fas fa-file-export"></i> Format Tujuan</label>
                <select id="convertFormat" class="select-styled" style="width:100%; border:1px solid var(--border-color); background:var(--bg-input); padding:10px; color:var(--text-primary); border-radius:var(--radius-sm);">
                    <!-- Options populated dynamically by JS -->
                </select>
            </div>
            <div id="convertInfo" style="background:var(--bg-input);border-radius:var(--radius-sm);padding:10px;margin-bottom:10px;font-size:12px;color:var(--text-muted);display:none;">
                <i class="fas fa-info-circle" style="color:var(--cyan)"></i>
                <span id="convertInfoText"></span>
            </div>
            <div class="modal-footer" style="margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary" id="btnConvert"><i class="fas fa-exchange-alt"></i> Konversi</button>
            </div>
        </form>
    </div>
</div>

<input type="hidden" id="currentFolderId" value="<?= $folderId ?? '' ?>">

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
