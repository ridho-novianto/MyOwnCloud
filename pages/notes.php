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
    padding: 25px;
    font-family: 'Consolas', 'Courier New', monospace;
    font-size: 1.05rem;
    resize: none;
    outline: none;
    line-height: 1.7;
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
