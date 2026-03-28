<?php
/**
 * Notepad-like Notes Page
 */
$pageTitle = 'Notes';
$pageIcon = 'sticky-note';
$headerButton = ['text' => 'Catatan Baru', 'onclick' => 'newNote()', 'id' => 'addNoteBtn'];
$pageScripts = ['notes.js'];
require_once __DIR__ . '/../includes/header.php';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    tinymce.init({
        selector: '#noteContent',
        skin: 'oxide-dark',
        content_css: 'dark',
        height: '100%',
        promotion: false,
        branding: false,
        menubar: true,
        plugins: 'advlist autolink lists link image charmap preview anchor pagebreak searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking save table directionality emoticons template',
        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | outdent indent | numlist bullist | forecolor backcolor removeformat | pagebreak | charmap emoticons | fullscreen preview print | insertfile image media template link anchor codesample | ltr rtl',
        setup: function(editor) {
            editor.on('input change', function() {
                if (typeof markUnsaved === 'function') markUnsaved();
            });
        }
    });
});
</script>

<style>
.notes-container {
    display: flex;
    height: calc(100vh - 180px);
    gap: 20px;
}
.notes-sidebar {
    width: 320px;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.notes-list {
    flex: 1;
    overflow-y: auto;
}
.note-item {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    cursor: pointer;
    transition: background 0.2s, border-left 0.2s;
    border-left: 3px solid transparent;
}
.note-item:hover {
    background: rgba(255,255,255,0.02);
}
.note-item.active {
    background: rgba(0, 229, 255, 0.05);
    border-left: 3px solid var(--primary-color);
}
.note-title {
    font-weight: 600;
    margin-bottom: 6px;
    color: var(--text-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 1.05rem;
}
.note-date {
    font-size: 0.8rem;
    color: var(--text-muted);
}
.notes-editor-area {
    flex: 1;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}
.editor-toolbar {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(0,0,0,0.1);
}
.editor-title-input {
    background: transparent;
    border: none;
    color: var(--text-color);
    font-size: 1.25rem;
    font-weight: 600;
    width: 60%;
    outline: none;
    font-family: 'Inter', sans-serif;
}
.editor-title-input::placeholder {
    color: rgba(255,255,255,0.3);
}
.editor-actions {
    display: flex;
    gap: 12px;
}
.note-textarea {
    flex: 1;
    background: transparent;
    border: none;
    color: #e0e6ed;
    padding: 0;
    font-size: 1.05rem;
    resize: none;
    outline: none;
    line-height: 1.7;
}
/* Ensure the editor container takes full height minus toolbar */
.tox-tinymce {
    border: none !important;
    border-radius: 0 0 12px 12px !important;
}
.note-textarea::placeholder {
    color: rgba(255,255,255,0.2);
}
/* Empty state for editor */
.editor-empty {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background: var(--card-bg);
    color: var(--text-muted);
    z-index: 10;
}
.editor-empty i {
    font-size: 5rem;
    margin-bottom: 25px;
    color: var(--primary-color);
    opacity: 0.3;
}
.editor-empty h3 {
    font-weight: 500;
}
@media (max-width: 768px) {
    .notes-container {
        flex-direction: column;
        height: auto;
        min-height: calc(100vh - 160px);
        gap: 10px;
    }
    .notes-sidebar {
        width: 100%;
        max-height: 200px;
        margin-bottom: 0;
        flex-shrink: 0;
        border-radius: 8px;
    }
    .note-item {
        padding: 10px 14px;
    }
    .note-title {
        font-size: 0.9rem;
        margin-bottom: 3px;
    }
    .note-date {
        font-size: 0.7rem;
    }
    .notes-editor-area {
        min-height: 450px;
        flex: 1 1 auto;
        border-radius: 8px;
    }
    .editor-toolbar {
        flex-wrap: wrap;
        gap: 8px;
        padding: 10px 12px;
    }
    .editor-title-input {
        width: 100%;
        font-size: 1rem;
        order: 1;
    }
    .editor-actions {
        width: 100%;
        justify-content: flex-end;
        gap: 8px;
        order: 2;
    }
    .editor-actions .btn {
        font-size: 11px;
        padding: 6px 10px;
    }
    .editor-empty i {
        font-size: 3rem;
        margin-bottom: 15px;
    }
    .editor-empty h3 {
        font-size: 0.95rem;
    }
    .editor-empty p {
        font-size: 0.8rem;
    }
    /* TinyMCE mobile fixes */
    .tox-tinymce {
        min-height: 668px !important;
    }
    .tox .tox-toolbar__group {
        flex-wrap: wrap !important;
    }
    .tox .tox-menubar {
        flex-wrap: wrap !important;
    }
}
</style>

<div class="notes-container">
    <div class="notes-sidebar">
        <div class="notes-list" id="notesList">
            <!-- Notes injected here via JS -->
            <div style="padding: 30px; text-align: center; color: var(--text-muted);">
                <i class="fas fa-circle-notch fa-spin"></i> Memuat catatan...
            </div>
        </div>
    </div>
    <div class="notes-editor-area">
        <div class="editor-empty" id="editorEmptyState">
            <i class="fas fa-file-signature"></i>
            <h3>Pilih catatan atau buat yang baru</h3>
            <p style="margin-top: 10px; font-size: 0.9rem; opacity: 0.7;">Catatan Anda disinkronkan ke cloud secara otomatis</p>
        </div>
        
        <div class="editor-toolbar">
            <input type="text" class="editor-title-input" id="noteTitle" placeholder="Judul Catatan Tanpa Nama" oninput="markUnsaved()">
            <div class="editor-actions">
                <button class="btn btn-secondary btn-sm" onclick="deleteCurrentNote()" id="btnDeleteNote" style="display: none;">
                    <i class="fas fa-trash"></i> Hapus
                </button>
                <button class="btn btn-primary btn-sm" onclick="saveNote()" id="btnSaveNote">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </div>
        <textarea class="note-textarea" id="noteContent" placeholder="Mulai mengetik catatan Anda di sini... (mendukung teks biasa)" oninput="markUnsaved()"></textarea>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
