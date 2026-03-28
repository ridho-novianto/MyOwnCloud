/**
 * File Manager JS
 */

const uploadZone = document.getElementById('uploadZone');
const fileInput = document.getElementById('fileInput');

// Drag and drop
if (uploadZone) {
    uploadZone.addEventListener('click', () => fileInput.click());

    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.classList.add('dragover');
    });

    uploadZone.addEventListener('dragleave', () => {
        uploadZone.classList.remove('dragover');
    });

    uploadZone.addEventListener('drop', async (e) => {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
        
        const items = e.dataTransfer.items;
        if (items && items.length > 0 && items[0].webkitGetAsEntry) {
            const filesToUpload = [];
            
            const getFile = (entry, path = '') => {
                return new Promise(resolve => {
                    if (entry.isFile) {
                        entry.file(file => {
                            Object.defineProperty(file, 'webkitRelativePath', {
                                value: path + file.name
                            });
                            filesToUpload.push(file);
                            resolve();
                        });
                    } else if (entry.isDirectory) {
                        const reader = entry.createReader();
                        const readAllEntries = () => {
                            reader.readEntries(async entries => {
                                if (entries.length > 0) {
                                    for (let i = 0; i < entries.length; i++) {
                                        await getFile(entries[i], path + entry.name + '/');
                                    }
                                    readAllEntries();
                                } else {
                                    resolve();
                                }
                            });
                        };
                        readAllEntries();
                    }
                });
            };

            for (let i = 0; i < items.length; i++) {
                const entry = items[i].webkitGetAsEntry();
                if (entry) await getFile(entry);
            }
            
            handleFileSelect(filesToUpload);
        } else {
            handleFileSelect(e.dataTransfer.files);
        }
    });
}

async function handleFileSelect(files) {
    if (!files || files.length === 0) return;

    const formData = new FormData();
    formData.append('action', 'upload');
    const folderId = document.getElementById('currentFolderId')?.value;
    if (folderId) formData.append('folder_id', folderId);

    for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i]);
        formData.append('paths[]', files[i].webkitRelativePath || '');
    }

    const progress = document.getElementById('uploadProgress');
    const progressFill = document.getElementById('uploadProgressFill');
    const progressText = document.getElementById('uploadProgressText');
    progress.style.display = 'block';

    try {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', APP_URL + '/?page=api/files');

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const pct = Math.round((e.loaded / e.total) * 100);
                progressFill.style.width = pct + '%';
                progressText.textContent = `Uploading... ${pct}%`;
            }
        };

        xhr.onload = () => {
            progress.style.display = 'none';
            try {
                const data = JSON.parse(xhr.responseText);
                if (data.success) {
                    showToast('File berhasil diupload');
                    location.reload();
                } else {
                    showToast(data.error || 'Upload gagal', 'error');
                }
            } catch(e) {
                showToast('Upload gagal', 'error');
            }
        };

        xhr.onerror = () => {
            progress.style.display = 'none';
            showToast('Upload gagal', 'error');
        };

        xhr.send(formData);
    } catch(err) {
        progress.style.display = 'none';
        showToast('Upload gagal', 'error');
    }
}

async function deleteFile(id) {
    if (!confirm('Hapus file ini?')) return;
    try {
        const res = await apiCall('files', { action: 'delete', id });
        if (res.success) {
            showToast('File dihapus');
            document.querySelector(`.file-item[data-id="${id}"]`)?.remove();
        }
    } catch(err) {
        showToast('Gagal menghapus', 'error');
    }
}

function openFolderModal() {
    document.getElementById('folderName').value = '';
    openModal('folderModal');
}

async function createFolder(e) {
    e.preventDefault();
    const name = document.getElementById('folderName').value;
    const parentId = document.getElementById('currentFolderId')?.value;

    try {
        const res = await apiCall('files', { action: 'create_folder', name, parent_id: parentId || null });
        if (res.success) {
            showToast('Folder dibuat');
            closeModal();
            location.reload();
        } else {
            showToast(res.error || 'Gagal membuat folder', 'error');
        }
    } catch(err) {
        showToast('Gagal membuat folder', 'error');
    }
}

async function renameFolder(id, currentName) {
    const newName = prompt('Nama folder baru:', currentName);
    if (!newName || newName === currentName) return;

    try {
        const res = await apiCall('files', { action: 'rename_folder', id, name: newName });
        if (res.success) {
            showToast('Folder direname');
            location.reload();
        }
    } catch(err) {
        showToast('Gagal rename folder', 'error');
    }
}

async function deleteFolder(id) {
    if (!confirm('Hapus folder ini beserta isinya?')) return;
    try {
        const res = await apiCall('files', { action: 'delete_folder', id });
        if (res.success) {
            showToast('Folder dihapus');
            document.querySelector(`.file-item[data-id="${id}"]`)?.remove();
        }
    } catch(err) {
        showToast('Gagal menghapus', 'error');
    }
}

function previewFile(id, name, mime, filename) {
    const body = document.getElementById('previewBody');
    document.getElementById('previewTitle').textContent = name;

    if (mime.startsWith('image/')) {
        body.innerHTML = `<img src="${APP_URL}/uploads/${document.querySelector('[data-id]')?.closest('[data-id]') ? '' : ''}${id ? '' : ''}${filename.includes('/') ? filename : currentUserId() + '/' + filename}" alt="${name}" style="max-width:100%;border-radius:8px">`;
        // Simpler approach
        body.innerHTML = `<img src="${APP_URL}/?page=api/files&action=download&id=${id}" alt="${name}" style="max-width:100%;border-radius:8px">`;
    } else if (mime.startsWith('video/')) {
        body.innerHTML = `<video controls style="max-width:100%"><source src="${APP_URL}/?page=api/files&action=download&id=${id}" type="${mime}"></video>`;
    } else if (mime.startsWith('audio/')) {
        body.innerHTML = `<audio controls style="width:100%"><source src="${APP_URL}/?page=api/files&action=download&id=${id}" type="${mime}"></audio>`;
    } else if (mime === 'application/pdf') {
        body.innerHTML = `<iframe src="${APP_URL}/?page=api/files&action=download&id=${id}" style="width:100%;height:500px;border:none;border-radius:8px"></iframe>`;
    } else {
        body.innerHTML = `<div class="preview-unsupported"><i class="fas fa-file fa-3x"></i><p>Preview tidak tersedia untuk tipe file ini</p><a href="${APP_URL}/?page=api/files&action=download&id=${id}" class="btn btn-primary"><i class="fas fa-download"></i> Download</a></div>`;
    }

    openModal('previewModal');
}

function setView(view) {
    const grid = document.getElementById('fileGrid');
    if (view === 'list') {
        grid.classList.add('list-view');
        document.getElementById('gridViewBtn')?.classList.remove('active');
        document.getElementById('listViewBtn')?.classList.add('active');
    } else {
        grid.classList.remove('list-view');
        document.getElementById('gridViewBtn')?.classList.add('active');
        document.getElementById('listViewBtn')?.classList.remove('active');
    }
}

// --- Drag and Drop Move ---
let draggedItem = null;

function handleDragStartFile(e) {
    const item = e.target.closest('.file-item');
    if (!item) return;
    
    draggedItem = {
        type: item.dataset.itemtype,
        id: item.dataset.id
    };
    
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', JSON.stringify(draggedItem));
    
    setTimeout(() => {
        item.style.opacity = '0.5';
    }, 0);
}

document.addEventListener('dragend', (e) => {
    if (e.target.classList && e.target.classList.contains('file-item')) {
        e.target.style.opacity = '1';
    }
});

function handleDragOverFile(e) {
    e.preventDefault();
    const target = e.currentTarget;
    
    if (draggedItem && (target.dataset.itemtype === 'folder' || target.classList.contains('up-directory'))) {
        if (draggedItem.type === 'folder' && draggedItem.id === target.dataset.id) return;
        
        e.dataTransfer.dropEffect = 'move';
        target.style.borderColor = 'var(--cyan)';
        target.style.background = 'var(--cyan-bg)';
        target.style.transform = 'translateY(-2px)';
    }
}

function handleDragLeaveFile(e) {
    const target = e.currentTarget;
    target.style.borderColor = '';
    target.style.background = '';
    target.style.transform = '';
}

async function handleDropMove(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const target = e.currentTarget;
    target.style.borderColor = '';
    target.style.background = '';
    target.style.transform = '';
    
    if (!draggedItem) return;
    
    const targetFolderId = target.dataset.id;
    
    if (draggedItem.type === 'folder' && draggedItem.id === targetFolderId) return;
    
    try {
        const res = await apiCall('files', {
            action: 'move',
            type: draggedItem.type,
            item_id: draggedItem.id,
            target_id: targetFolderId || null
        });
        
        if (res.success) {
            showToast('Item berhasil dipindahkan');
            location.reload();
        } else {
            showToast(res.error || 'Gagal memindahkan', 'error');
        }
    } catch (err) {
        showToast('Gagal memindahkan', 'error');
    }
    
    draggedItem = null;
}

// --- File Converter ---
const CONVERT_FORMATS = {
    audio: [
        { value: 'mp3', label: 'Audio MP3 (.mp3)' },
        { value: 'wav', label: 'Audio WAV (.wav)' },
        { value: 'ogg', label: 'Audio OGG (.ogg)' },
        { value: 'aac', label: 'Audio AAC (.aac)' }
    ],
    video: [
        { value: 'mp4', label: 'Video MP4 (.mp4)' },
        { value: 'webm', label: 'Video WebM (.webm)' },
        { value: 'mp3', label: 'Extract Audio MP3 (.mp3)' }
    ],
    image: [
        { value: 'pdf', label: 'PDF Document (.pdf)' },
        { value: 'jpg', label: 'Gambar JPEG (.jpg)' },
        { value: 'png', label: 'Gambar PNG (.png)' },
        { value: 'webp', label: 'Gambar WebP (.webp)' }
    ],
    document: [
        { value: 'pdf', label: 'PDF Document (.pdf)' }
    ]
};

const CONVERT_INFO = {
    audio: 'Konversi menggunakan FFmpeg',
    video: 'Konversi menggunakan FFmpeg',
    image: 'Konversi gambar menggunakan PHP GD',
    document: 'Konversi dokumen menggunakan LibreOffice'
};

function getFileCategory(mime) {
    if (mime.startsWith('audio/')) return 'audio';
    if (mime.startsWith('video/')) return 'video';
    if (mime.startsWith('image/')) return 'image';
    return 'document';
}

function openConvertModal(id, mime) {
    document.getElementById('convertFileId').value = id;
    document.getElementById('convertFileMime').value = mime || '';

    const category = getFileCategory(mime || '');
    const formats = CONVERT_FORMATS[category] || CONVERT_FORMATS.document;
    const select = document.getElementById('convertFormat');

    select.innerHTML = '';
    formats.forEach(f => {
        const opt = document.createElement('option');
        opt.value = f.value;
        opt.textContent = f.label;
        select.appendChild(opt);
    });

    const infoDiv = document.getElementById('convertInfo');
    const infoText = document.getElementById('convertInfoText');
    if (CONVERT_INFO[category]) {
        infoDiv.style.display = 'block';
        infoText.textContent = CONVERT_INFO[category];
    } else {
        infoDiv.style.display = 'none';
    }

    openModal('convertModal');
}

async function handleConvert(e) {
    e.preventDefault();
    const id = document.getElementById('convertFileId').value;
    const format = document.getElementById('convertFormat').value;
    const btn = document.getElementById('btnConvert');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Proses...';

    try {
        const res = await apiCall('files', { action: 'convert', id, format });
        if (res.success) {
            showToast('Konversi berhasil! File baru: ' + (res.filename || format.toUpperCase()));
            location.reload();
        } else {
            showToast(res.error || 'Gagal mengkonversi', 'error');
        }
    } catch (err) {
        showToast('Terjadi kesalahan jaringan', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-exchange-alt"></i> Konversi';
        closeModal();
    }
}

