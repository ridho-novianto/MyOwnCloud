/**
 * Notes App Logic
 */

let currentNoteId = null;
let customNotes = [];
let isUnsaved = false;

document.addEventListener('DOMContentLoaded', () => {
    loadNotes();
    
    // Auto-save logic every 30 seconds if unsaved as a convenience
    setInterval(() => {
        if (isUnsaved && currentNoteId) {
            saveNote(true);
        }
    }, 30000);
});

function markUnsaved() {
    isUnsaved = true;
    const saveBtn = document.getElementById('btnSaveNote');
    if (saveBtn) {
        saveBtn.innerHTML = '<i class="fas fa-save"></i> Simpan*';
    }
}

function clearUnsaved() {
    isUnsaved = false;
    const saveBtn = document.getElementById('btnSaveNote');
    if (saveBtn) {
        saveBtn.innerHTML = '<i class="fas fa-check"></i> Tersimpan';
        setTimeout(() => {
            if (!isUnsaved) saveBtn.innerHTML = '<i class="fas fa-save"></i> Simpan';
        }, 2000);
    }
}

async function loadNotes() {
    try {
        const res = await fetch(`${APP_URL}/?page=api/notes`);
        const data = await res.json();
        
        if (data.notes) {
            customNotes = data.notes;
            renderNotesList();
        }
    } catch (e) {
        console.error('Failed to load notes', e);
    }
}

function renderNotesList() {
    const list = document.getElementById('notesList');
    if (!list) return;
    
    if (customNotes.length === 0) {
        list.innerHTML = `
            <div style="padding: 40px 20px; text-align: center; color: var(--text-muted);">
                <i class="fas fa-sticky-note" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.2; display: block;"></i>
                <p>Belum ada catatan.</p>
                <button class="btn btn-sm btn-primary" style="margin-top: 15px;" onclick="newNote()">Buat Sekarang</button>
            </div>`;
        return;
    }
    
    list.innerHTML = '';
    customNotes.forEach(note => {
        const date = new Date(note.updated_at).toLocaleDateString('id-ID', {
            year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
        });
        
        const div = document.createElement('div');
        div.className = `note-item ${currentNoteId === parseInt(note.id) ? 'active' : ''}`;
        div.onclick = () => openNote(note.id);
        
        div.innerHTML = `
            <div class="note-title">${escapeNotesHtml(note.title) || 'Catatan Tanpa Nama'}</div>
            <div class="note-date">${date}</div>
        `;
        list.appendChild(div);
    });
}

function newNote() {
    if (isUnsaved) {
        if (!confirm('Ada perubahan yang belum disimpan. Tetap buat catatan baru dan hilangkan perubahan yang belum disimpan?')) return;
    }
    
    currentNoteId = null;
    document.getElementById('noteTitle').value = '';
    document.getElementById('noteContent').value = '';
    document.getElementById('btnDeleteNote').style.display = 'none';
    
    document.getElementById('editorEmptyState').style.display = 'none';
    
    renderNotesList(); // Removes active state
    clearUnsaved();
    document.getElementById('noteTitle').focus();
}

function openNote(id) {
    if (isUnsaved) {
        if (!confirm('Ada perubahan yang belum disimpan. Pindah catatan dan hilangkan perubahan yang belum disimpan?')) return;
    }
    
    const note = customNotes.find(n => parseInt(n.id) === parseInt(id));
    if (!note) return;
    
    currentNoteId = parseInt(note.id);
    document.getElementById('noteTitle').value = note.title;
    document.getElementById('noteContent').value = note.content;
    document.getElementById('btnDeleteNote').style.display = 'inline-flex';
    
    document.getElementById('editorEmptyState').style.display = 'none';
    
    renderNotesList(); // Update active state
    clearUnsaved();
}

async function saveNote(isAutoSave = false) {
    const title = document.getElementById('noteTitle').value.trim() || 'Catatan Tanpa Nama';
    const content = document.getElementById('noteContent').value;
    
    const action = currentNoteId ? 'update' : 'create';
    const payload = {
        action,
        title,
        content
    };
    
    if (currentNoteId) {
        payload.id = currentNoteId;
    }
    
    try {
        if (!isAutoSave) {
            const saveBtn = document.getElementById('btnSaveNote');
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
            saveBtn.disabled = true;
        }
        
        const res = await fetch(`${APP_URL}/?page=api/notes`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        
        const data = await res.json();
        
        if (data.success) {
            if (action === 'create') {
                currentNoteId = data.id;
                document.getElementById('btnDeleteNote').style.display = 'inline-flex';
            }
            clearUnsaved();
            await loadNotes();
        } else {
            alert(data.error || 'Gagal menyimpan catatan');
        }
    } catch (e) {
        console.error(e);
        if (!isAutoSave) alert('Terjadi kesalahan sistem saat menyimpan');
    } finally {
        if (!isAutoSave) {
            const saveBtn = document.getElementById('btnSaveNote');
            saveBtn.disabled = false;
        }
    }
}

async function deleteCurrentNote() {
    if (!currentNoteId) return;
    if (!confirm('Anda yakin ingin menghapus catatan ini secara permanen?')) return;
    
    try {
        const res = await fetch(`${APP_URL}/?page=api/notes`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'delete', id: currentNoteId })
        });
        
        const data = await res.json();
        if (data.success) {
            currentNoteId = null;
            document.getElementById('editorEmptyState').style.display = 'flex';
            await loadNotes();
        } else {
            alert(data.error || 'Gagal menghapus catatan');
        }
    } catch (e) {
        console.error(e);
        alert('Terjadi kesalahan sistem saat menghapus');
    }
}

// Named specific to notes app to avoid conflict with potential global escapeHtml
function escapeNotesHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}
