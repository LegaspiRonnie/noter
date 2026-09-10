const API_URL = '../api/Note.php';


export async function index() {
    try {
        const notesTableBody = document.querySelector('#notesTableBody');
        const response = await fetch(API_URL, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();
        const notes = Array.isArray(result) ? result : (result.data || []);

        if (!notesTableBody) {
            throw new Error('Table body not found.');
        }

        if (notes.length === 0) {
            notesTableBody.innerHTML = '<tr><td colspan="6">No notes found.</td></tr>';
            return;
        }

        notesTableBody.innerHTML = notes.map(note => {
            return `
                <tr>
                    <td>${note.id ?? ''}</td>
                    <td>${note.category_id ?? ''}</td>
                    <td>${note.title ?? ''}</td>
                    <td>${note.description ?? ''}</td>
                    <td>${note.created_at ?? ''}</td>
                    <td>
                        <button type="button" data-action="view" data-id="${note.id}">View</button>
                        <button type="button" data-action="edit" data-id="${note.id}">Edit</button>
                        <button type="button" data-action="delete" data-id="${note.id}">Delete</button>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (error) {
        if (notesTableBody) {
            notesTableBody.innerHTML = `<tr><td colspan="6">${error.message}</td></tr>`;
        }
        console.error(error);
    }
}

export async function viewNote(id) {
    try {
        const noteDetailsModal = document.querySelector('#notesDetailsModal');

        if (!noteDetailsModal) {
            throw new Error('Modal element not found.');
        }

        const response = await fetch(`${API_URL}?id=${id}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();
        const note = result.data || result;

        noteDetailsModal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <span class="close">&times;</span>
                    <h2>Note Details</h2>
                </div>
                <div class="modal-body">
                    <p><strong>ID:</strong> ${note.id ?? 'N/A'}</p>
                    <p><strong>Category:</strong> ${note.category_id ?? 'N/A'}</p>
                    <p><strong>Title:</strong> ${note.title ?? 'N/A'}</p>
                    <p><strong>Description:</strong> ${note.description ?? 'No description'}</p>
                    <p><strong>Created:</strong> ${note.created_at ?? 'N/A'}</p>
                </div>
            </div>
        `;

        noteDetailsModal.style.display = 'block';

        const closeButton = noteDetailsModal.querySelector('.close');
        if (closeButton) {
            closeButton.addEventListener('click', () => {
                noteDetailsModal.style.display = 'none';
            });
        }
    } catch (error) {
        console.error(error);
        alert(error.message);
    }
}
export async function addNote() {
    const addNoteModal = document.querySelector('#addNoteModal');

    if (!addNoteModal) {
        throw new Error('Add Note modal element not found.');
    }

    const response = await fetch(API_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    addNoteModal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <span class="close">&times;</span>
                    <h2>Note Details</h2>
                </div>
                <div class="modal-body">
                    <p><strong>ID:</strong> ${note.id ?? 'N/A'}</p>
                    <p><strong>Category:</strong> ${note.category_id ?? 'N/A'}</p>
                    <p><strong>Title:</strong> ${note.title ?? 'N/A'}</p>
                    <p><strong>Description:</strong> ${note.description ?? 'No description'}</p>
                    <p><strong>Created:</strong> ${note.created_at ?? 'N/A'}</p>
                </div>
            </div>
        `;

    addNoteModal.style.display = 'block';
}

export function bindActions() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('button');

        if (!button) {
            return;
        }

        const action = button.dataset.action;
        const id = button.dataset.id;

        if (!action || !id) {
            return;
        }

        if (action === 'view') {
            await viewNote(id);
        }

        if (action === 'edit') {
            alert(`Edit note with id: ${id}`);
        }

        if (action === 'delete') {
            alert(`Delete note with id: ${id}`);
        }
    });
}
