const API_URL = (typeof window !== 'undefined' && window.APP_CONFIG && window.APP_CONFIG.API_URL)
    ? window.APP_CONFIG.API_URL
    : '../backend/api/Note.php';

function showAlert(message, icon = 'success') {
    if (window.Swal) {
        return window.Swal.fire({
            icon,
            text: message,
            confirmButtonText: 'OK',
            confirmButtonColor: '#2563eb'
        });
    }

    alert(message);
    return Promise.resolve();
}

function confirmAction(message, title = 'Are you sure?') {
    if (window.Swal) {
        return window.Swal.fire({
            title,
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, continue',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc2626'
        });
    }

    return Promise.resolve({ isConfirmed: confirm(message) });
}

function escapeHtml(value = '') {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function normalizeHighlightMarkup(value = '') {
    let html = String(value ?? '')
        .replace(/&lt;/gi, '<')
        .replace(/&gt;/gi, '>')
        .replace(/&quot;/gi, '"')
        .replace(/&#39;/gi, "'")
        .replace(/&amp;/gi, '&');

    html = html.replace(/<script[\s\S]*?<\/script>/gi, '');

    html = html.replace(/<\/?(script|style|iframe|object|embed|svg|math|img|video|audio|canvas|link|meta|base|form|input|button|select|textarea|option|noscript|article|aside|details|figcaption|figure|header|footer|nav|main|section)[^>]*>/gi, '');
    html = html.replace(/<(?!\/?(mark|br|strong|b|em|i|u|p|span)\b)[^>]*>/gi, '');

    return html.replace(/\n/g, '<br>');
}

function formatDescriptionForDisplay(value = '') {
    const safeMarkup = normalizeHighlightMarkup(value);
    return safeMarkup || 'No description';
}

function getSelectedRangeInEditor(editor) {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
        return null;
    }

    const range = selection.getRangeAt(0);
    const selectedText = selection.toString().trim();

    if (!selectedText || range.collapsed) {
        return null;
    }

    if (!editor.contains(range.startContainer) && !editor.contains(range.endContainer)) {
        return null;
    }

    return range.cloneRange();
}

function restoreSelectionFromRange(editor, range) {
    const selection = window.getSelection();
    if (!selection || !range) {
        return;
    }

    selection.removeAllRanges();
    selection.addRange(range);
    editor.focus();
}

function applyHighlightSelection(editor, color = '#fef08a') {
    if (!editor) {
        return;
    }

    const selection = window.getSelection();
    const range = getSelectedRangeInEditor(editor);

    if (!selection || !range) {
        showAlert('Select some text first, then highlight it.', 'warning');
        return;
    }

    const clonedRange = range.cloneRange();
    const selectedContent = clonedRange.extractContents();

    if (!selectedContent.textContent || selectedContent.textContent.trim() === '') {
        showAlert('Select some text first, then highlight it.', 'warning');
        return;
    }

    const mark = document.createElement('mark');
    mark.style.backgroundColor = color;
    mark.appendChild(selectedContent);
    clonedRange.insertNode(mark);
    restoreSelectionFromRange(editor, range);
}

function removeHighlightSelection(editor) {
    if (!editor) {
        return;
    }

    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
        showAlert('Select highlighted text first, then remove the highlight.', 'warning');
        return;
    }

    const range = selection.getRangeAt(0);
    let highlightNode = null;

    if (range.commonAncestorContainer.nodeType === 1) {
        highlightNode = range.commonAncestorContainer.closest('mark');
    } else if (range.commonAncestorContainer.parentElement) {
        highlightNode = range.commonAncestorContainer.parentElement.closest('mark');
    }

    if (!highlightNode) {
        showAlert('Select highlighted text first, then remove the highlight.', 'warning');
        return;
    }

    const parent = highlightNode.parentNode;
    while (highlightNode.firstChild) {
        parent.insertBefore(highlightNode.firstChild, highlightNode);
    }

    parent.removeChild(highlightNode);
    selection.removeAllRanges();
    editor.focus();
}

async function readJsonResponse(response, fallbackMessage = 'Request failed.') {
    const text = await response.text();

    if (!text) {
        return {};
    }

    try {
        return JSON.parse(text);
    } catch (error) {
        console.error('Invalid JSON response:', text);
        throw new Error(fallbackMessage);
    }
}

function normalizeResult(result) {
    if (result && typeof result === 'object' && result.status === 'error') {
        throw new Error(result.message || 'Something went wrong.');
    }

    return result;
}

function renderLoadingState(message = 'Loading notes...') {
    return `
        <div class="loading-state" aria-live="polite">
            <span class="loader" aria-hidden="true"></span>
            <span>${escapeHtml(message)}</span>
        </div>
    `;
}

export async function index(searchTerm = '', page = 1, categoryId = '') {
    const notesGrid = document.querySelector('#notesGrid');
    const paginationContainer = document.querySelector('#paginationControls');
    const categoryFilter = document.querySelector('#categoryFilter');

    try {
        const query = (searchTerm ?? '').trim();
        const currentPage = Number(page) > 0 ? Number(page) : 1;
        const selectedCategoryId = categoryId !== undefined && categoryId !== null ? String(categoryId) : (categoryFilter ? categoryFilter.value : '');

        if (notesGrid) {
            notesGrid.innerHTML = renderLoadingState('Loading notes...');
            if (categoryFilter) {
                categoryFilter.disabled = true;
            }
        }

        const params = new URLSearchParams();

        if (query) params.set('search', query);
        if (selectedCategoryId) params.set('category_id', selectedCategoryId);
        params.set('page', String(currentPage));
        params.set('limit', '10');

        const categories = await loadCategories();
        if (categoryFilter) {
            const options = '<option value="">All categories</option>' + renderCategoryOptions(categories, selectedCategoryId);
            categoryFilter.innerHTML = options;
            categoryFilter.value = selectedCategoryId || '';
        }

        const response = await fetch(`${API_URL}?${params.toString()}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = normalizeResult(await readJsonResponse(response, 'Failed to load notes.'));
        const notes = Array.isArray(result && result.items) ? result.items : (Array.isArray(result && result.data) ? result.data : []);
        const pagination = result && result.pagination ? result.pagination : { current_page: currentPage, total_pages: 1, per_page: 10, total: notes.length };

        if (!notesGrid) {
            throw new Error('Notes grid not found.');
        }

        if (notes.length === 0) {
            notesGrid.innerHTML = '<div class="empty-message">No notes found.</div>';
            if (paginationContainer) paginationContainer.innerHTML = '';
            return;
        }

        const groupedNotes = new Map();
        for (const note of notes) {
            const categoryName = note.category_name || (note.category_id ? `Category ${note.category_id}` : 'Uncategorized');
            if (!groupedNotes.has(categoryName)) {
                groupedNotes.set(categoryName, []);
            }
            groupedNotes.get(categoryName).push(note);
        }

        const orderedGroups = [...groupedNotes.entries()].sort(([left], [right]) => left.localeCompare(right));

        notesGrid.innerHTML = orderedGroups.map(([categoryName, grouped]) => {
            const groupCards = grouped.map(note => {
                const safeTitle = escapeHtml(note.title ?? 'Untitled');
                const safeDescription = formatDescriptionForDisplay(note.description ?? 'No description');
                const safeCategory = escapeHtml(note.category_name || (note.category_id ? `Category ${note.category_id}` : 'Uncategorized'));
                const safeCreatedAt = escapeHtml(note.created_at ?? '');
                const pinned = Number(note.pinned ?? 0) === 1;
                const pinLabel = pinned ? 'Unpin' : 'Pin';

                return `
                    <article class="note-card">
                        <div class="note-header">
                            <span class="category-pill">${safeCategory}</span>
                            ${pinned ? '<span class="pin-badge">Pinned</span>' : ''}
                        </div>
                        <h3>${safeTitle}</h3>
                        <p>${safeDescription}</p>
                        <div class="note-meta">${safeCreatedAt}</div>
                        <div class="card-actions">
                            <button type="button" data-action="view" data-id="${note.id}">View</button>
                            <button type="button" data-action="edit" data-id="${note.id}">Edit</button>
                            <button type="button" data-action="toggle-pin" data-id="${note.id}" data-pinned="${pinned ? '1' : '0'}">${pinLabel}</button>
                            <button type="button" data-action="delete" data-id="${note.id}">Delete</button>
                        </div>
                    </article>
                `;
            }).join('');

            return `
                <section class="category-group" data-category="${escapeHtml(categoryName)}">
                    <h2 class="category-group-title">${escapeHtml(categoryName)}</h2>
                    <div class="notes-grid-inner">${groupCards}</div>
                </section>
            `;
        }).join('');

        if (categoryFilter) {
            categoryFilter.disabled = false;
        }

        if (paginationContainer) {
            const totalPages = Number(pagination.total_pages || 1);
            const currentPageNum = Number(pagination.current_page || currentPage);
            const pages = [];

            for (let pageIndex = 1; pageIndex <= totalPages; pageIndex++) {
                pages.push(`<button type="button" class="page-btn ${pageIndex === currentPageNum ? 'active' : ''}" data-page="${pageIndex}">${pageIndex}</button>`);
            }

            paginationContainer.innerHTML = `
                <div class="pagination-wrap">
                    <button type="button" class="page-btn" data-page="${Math.max(1, currentPageNum - 1)}" ${currentPageNum <= 1 ? 'disabled' : ''}>Prev</button>
                    ${pages.join('')}
                    <button type="button" class="page-btn" data-page="${Math.min(totalPages, currentPageNum + 1)}" ${currentPageNum >= totalPages ? 'disabled' : ''}>Next</button>
                </div>
            `;
        }
    } catch (error) {
        if (notesGrid) {
            notesGrid.innerHTML = `<div class="error-message">${escapeHtml(error.message)}</div>`;
        }
        if (paginationContainer) paginationContainer.innerHTML = '';
        if (categoryFilter) {
            categoryFilter.disabled = false;
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

        const result = normalizeResult(await readJsonResponse(response, 'Failed to load note details.'));
        const note = result.data || result;

        noteDetailsModal.innerHTML = `
            <div class="modal-content paper-note">
                <div class="paper-header">
                    <span class="category-pill">${escapeHtml(note.category_name || note.category_id || 'Uncategorized')}</span>
                    <span class="paper-meta">${escapeHtml(note.created_at ?? 'N/A')}</span>
                    <span class="close" aria-label="Close note">&times;</span>
                </div>
                <div class="paper-body">
                    <h2 class="paper-title">${escapeHtml(note.title ?? 'Untitled')}</h2>
                    <p class="paper-description">${formatDescriptionForDisplay(note.description ?? 'No description')}</p>
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
        showAlert(error.message, 'error');
    }
}

async function loadCategories() {
    try {
        const response = await fetch(`${API_URL}?action=categories`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = normalizeResult(await readJsonResponse(response, 'Failed to load categories.'));
        return Array.isArray(result) ? result : (result.data || []);
    } catch (error) {
        console.error(error);
        return [];
    }
}

function renderCategoryOptions(categories, selectedCategoryId = '') {
    if (!Array.isArray(categories) || categories.length === 0) {
        return '<option value="">No categories available</option>';
    }

    return categories.map(category => {
        const id = category.id;
        const label = category.name || `Category ${id}`;
        const selected = String(id) === String(selectedCategoryId) ? 'selected' : '';
        return `<option value="${escapeHtml(id)}" ${selected}>${escapeHtml(label)}</option>`;
    }).join('');
}

export async function addCategory() {
    const addCategoryModal = document.querySelector('#addCategoryModal') || document.createElement('div');
    addCategoryModal.id = 'addCategoryModal';
    addCategoryModal.className = 'modal';
    addCategoryModal.style.display = 'block';

    document.body.appendChild(addCategoryModal);

    addCategoryModal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" aria-label="Close">&times;</span>
                <h2>Add Category</h2>
            </div>
            <div class="modal-body">
                <form id="addCategoryForm">
                    <div>
                        <label for="categoryName">Category name</label>
                        <input type="text" id="categoryName" name="name" required placeholder="Enter category name">
                    </div>
                    <div>
                        <label for="categoryDescription">Description</label>
                        <textarea id="categoryDescription" name="description" rows="4" placeholder="Optional category description"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save category</button>
                </form>
            </div>
        </div>
    `;

    const closeButton = addCategoryModal.querySelector('.close');
    if (closeButton) {
        closeButton.addEventListener('click', () => {
            addCategoryModal.style.display = 'none';
        });
    }

    const form = addCategoryModal.querySelector('#addCategoryForm');
    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const formData = new FormData(form);
            const payload = {
                name: (formData.get('name') || '').toString().trim(),
                description: (formData.get('description') || '').toString().trim(),
            };

            if (!payload.name) {
                showAlert('Category name is required.', 'warning');
                return;
            }

            try {
                const response = await fetch(`${API_URL}?action=categories`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const result = normalizeResult(await readJsonResponse(response, 'Failed to create category.'));

                if (!response.ok || result.status === 'error') {
                    throw new Error(result.message || 'Failed to create category.');
                }

                addCategoryModal.style.display = 'none';
                showAlert(result.message || 'Category created successfully.', 'success');
                await index();
            } catch (error) {
                console.error(error);
                showAlert(error.message, 'error');
            }
        });
    }
}

export async function addNote() {
    const addNoteModal = document.querySelector('#addNoteModal');

    if (!addNoteModal) {
        throw new Error('Add Note modal element not found.');
    }

    const categories = await loadCategories();
    const categoryOptions = renderCategoryOptions(categories);

    addNoteModal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <span class="close">&times;</span>
                <h2>Add Note</h2>
            </div>
            <div class="modal-body">
                <form id="addNoteForm">
                    <div>
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" ${categories.length === 0 ? 'disabled' : ''}>
                            <option value="">Select category</option>
                            ${categoryOptions}
                        </select>
                    </div>
                    <div>
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" required placeholder="Enter a note title">
                    </div>
                    <div>
                        <label for="description">Description</label>
                        <div class="description-editor-wrap">
                            <div class="toolbar">
                                <button type="button" class="btn btn-primary btn-small" data-action="highlight-selection">Highlight</button>
                                <button type="button" class="btn btn-small" data-action="remove-highlight">Unhighlight</button>
                                <button type="button" class="color-swatch" data-highlight-color="#fef08a" title="Yellow" style="background:#fef08a"></button>
                                <button type="button" class="color-swatch" data-highlight-color="#f9a8d4" title="Pink" style="background:#f9a8d4"></button>
                                <button type="button" class="color-swatch" data-highlight-color="#86efac" title="Green" style="background:#86efac"></button>
                                <button type="button" class="color-swatch" data-highlight-color="#93c5fd" title="Blue" style="background:#93c5fd"></button>
                            </div>
                            <div class="description-editor" data-role="description-editor" contenteditable="true" placeholder="Write your note..."></div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save note</button>
                </form>
            </div>
        </div>
    `;

    const closeButton = addNoteModal.querySelector('.close');
    if (closeButton) {
        closeButton.addEventListener('click', () => {
            addNoteModal.style.display = 'none';
        });
    }

    const addNoteForm = addNoteModal.querySelector('#addNoteForm');
    const descriptionEditor = addNoteForm ? addNoteForm.querySelector('[data-role="description-editor"]') : null;
    const highlightButton = addNoteForm ? addNoteForm.querySelector('[data-action="highlight-selection"]') : null;
    const removeHighlightButton = addNoteForm ? addNoteForm.querySelector('[data-action="remove-highlight"]') : null;
    const colorButtons = addNoteForm ? addNoteForm.querySelectorAll('[data-highlight-color]') : [];

    const preserveSelectionBeforeAction = (button) => {
        if (!descriptionEditor) {
            return;
        }

        button.addEventListener('mousedown', (event) => {
            event.preventDefault();
            descriptionEditor.focus();
            const selection = window.getSelection();
            if (selection && selection.rangeCount > 0) {
                const range = selection.getRangeAt(0).cloneRange();
                button.__savedRange = range;
            }
        });
    };

    if (highlightButton && descriptionEditor) {
        preserveSelectionBeforeAction(highlightButton);

        highlightButton.addEventListener('click', () => {
            const selection = window.getSelection();
            const currentRange = highlightButton.__savedRange || getSelectedRangeInEditor(descriptionEditor);

            if (selection && currentRange) {
                selection.removeAllRanges();
                selection.addRange(currentRange);
            }

            applyHighlightSelection(descriptionEditor);
        });
    }

    if (removeHighlightButton && descriptionEditor) {
        preserveSelectionBeforeAction(removeHighlightButton);

        removeHighlightButton.addEventListener('click', () => {
            const selection = window.getSelection();
            const currentRange = removeHighlightButton.__savedRange || getSelectedRangeInEditor(descriptionEditor);

            if (selection && currentRange) {
                selection.removeAllRanges();
                selection.addRange(currentRange);
            }

            removeHighlightSelection(descriptionEditor);
        });
    }

    colorButtons.forEach((button) => {
        preserveSelectionBeforeAction(button);

        button.addEventListener('click', () => {
            const color = button.dataset.highlightColor || '#fef08a';
            const selection = window.getSelection();
            const currentRange = button.__savedRange || getSelectedRangeInEditor(descriptionEditor);

            if (selection && currentRange) {
                selection.removeAllRanges();
                selection.addRange(currentRange);
            }

            applyHighlightSelection(descriptionEditor, color);
        });
    });

    if (addNoteForm) {
        addNoteForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const formData = new FormData(addNoteForm);
            const payload = {
                category_id: formData.get('category_id') || null,
                title: (formData.get('title') || '').toString().trim(),
                description: descriptionEditor ? descriptionEditor.innerHTML.trim() : '',
            };

            if (!payload.title) {
                showAlert('Title is required.', 'warning');
                return;
            }

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const result = normalizeResult(await readJsonResponse(response, 'Failed to create note.'));

                if (!response.ok || result.status === 'error') {
                    throw new Error(result.message || 'Failed to create note.');
                }

                addNoteModal.style.display = 'none';
                showAlert(result.message || 'Note created successfully.', 'success');
                await index();
            } catch (error) {
                console.error(error);
                showAlert(error.message, 'error');
            }
        });
    }

    addNoteModal.style.display = 'block';
}

export async function editNote(id) {
    const editNoteModal = document.querySelector('#editNoteModal');

    if (!editNoteModal) {
        throw new Error('Edit Note modal element not found.');
    }

    const categories = await loadCategories();

    const noteResponse = await fetch(`${API_URL}?id=${id}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    });

    if (!noteResponse.ok) {
        throw new Error(`HTTP error! status: ${noteResponse.status}`);
    }

    const noteResult = normalizeResult(await readJsonResponse(noteResponse, 'Failed to load note.'));
    const note = noteResult.data || noteResult;
    const selectedCategoryId = note.category_id ?? '';
    const categoryOptions = renderCategoryOptions(categories, selectedCategoryId);

    editNoteModal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <span class="close">&times;</span>
                <h2>Edit Note</h2>
            </div>
            <div class="modal-body">
                <form id="editNoteForm">
                    <div>
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" ${categories.length === 0 ? 'disabled' : ''}>
                            <option value="">Select category</option>
                            ${categoryOptions}
                        </select>
                    </div>
                    <div>
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" value="${escapeHtml(note.title ?? '')}" required>
                    </div>
                    <div>
                        <label for="description">Description</label>
                        <div class="description-editor-wrap">
                            <div class="toolbar">
                                <button type="button" class="btn btn-primary btn-small" data-action="highlight-selection">Highlight</button>
                                <button type="button" class="btn btn-small" data-action="remove-highlight">Unhighlight</button>
                                <button type="button" class="color-swatch" data-highlight-color="#fef08a" title="Yellow" style="background:#fef08a"></button>
                                <button type="button" class="color-swatch" data-highlight-color="#f9a8d4" title="Pink" style="background:#f9a8d4"></button>
                                <button type="button" class="color-swatch" data-highlight-color="#86efac" title="Green" style="background:#86efac"></button>
                                <button type="button" class="color-swatch" data-highlight-color="#93c5fd" title="Blue" style="background:#93c5fd"></button>
                            </div>
                            <div class="description-editor" data-role="description-editor" contenteditable="true">${String(note.description ?? '').replace(/\n/g, '<br>')}</div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save note</button>
                </form>
            </div>
        </div>
    `;

    const closeButton = editNoteModal.querySelector('.close');
    if (closeButton) {
        closeButton.addEventListener('click', () => {
            editNoteModal.style.display = 'none';
        });
    }

    const editNoteForm = editNoteModal.querySelector('#editNoteForm');
    const descriptionEditor = editNoteForm ? editNoteForm.querySelector('[data-role="description-editor"]') : null;
    const highlightButton = editNoteForm ? editNoteForm.querySelector('[data-action="highlight-selection"]') : null;
    const removeHighlightButton = editNoteForm ? editNoteForm.querySelector('[data-action="remove-highlight"]') : null;
    const colorButtons = editNoteForm ? editNoteForm.querySelectorAll('[data-highlight-color]') : [];

    const preserveSelectionBeforeAction = (button) => {
        if (!descriptionEditor) {
            return;
        }

        button.addEventListener('mousedown', (event) => {
            event.preventDefault();
            descriptionEditor.focus();
            const selection = window.getSelection();
            if (selection && selection.rangeCount > 0) {
                const range = selection.getRangeAt(0).cloneRange();
                button.__savedRange = range;
            }
        });
    };

    if (highlightButton && descriptionEditor) {
        preserveSelectionBeforeAction(highlightButton);

        highlightButton.addEventListener('click', () => {
            const selection = window.getSelection();
            const currentRange = highlightButton.__savedRange || getSelectedRangeInEditor(descriptionEditor);

            if (selection && currentRange) {
                selection.removeAllRanges();
                selection.addRange(currentRange);
            }

            applyHighlightSelection(descriptionEditor);
        });
    }

    if (removeHighlightButton && descriptionEditor) {
        preserveSelectionBeforeAction(removeHighlightButton);

        removeHighlightButton.addEventListener('click', () => {
            const selection = window.getSelection();
            const currentRange = removeHighlightButton.__savedRange || getSelectedRangeInEditor(descriptionEditor);

            if (selection && currentRange) {
                selection.removeAllRanges();
                selection.addRange(currentRange);
            }

            removeHighlightSelection(descriptionEditor);
        });
    }

    colorButtons.forEach((button) => {
        preserveSelectionBeforeAction(button);

        button.addEventListener('click', () => {
            const color = button.dataset.highlightColor || '#fef08a';
            const selection = window.getSelection();
            const currentRange = button.__savedRange || getSelectedRangeInEditor(descriptionEditor);

            if (selection && currentRange) {
                selection.removeAllRanges();
                selection.addRange(currentRange);
            }

            applyHighlightSelection(descriptionEditor, color);
        });
    });

    if (editNoteForm) {
        editNoteForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const formData = new FormData(editNoteForm);
            const payload = {
                id: Number(id),
                category_id: formData.get('category_id') || null,
                title: (formData.get('title') || '').toString().trim(),
                description: descriptionEditor ? descriptionEditor.innerHTML.trim() : '',
            };

            if (!payload.title) {
                showAlert('Title is required.', 'warning');
                return;
            }

            try {
                const response = await fetch(`${API_URL}?id=${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const result = normalizeResult(await readJsonResponse(response, 'Failed to edit note.'));

                if (!response.ok || result.status === 'error') {
                    throw new Error(result.message || 'Failed to edit note.');
                }

                editNoteModal.style.display = 'none';
                showAlert(result.message || 'Note edited successfully.', 'success');
                await index();
            } catch (error) {
                console.error(error);
                showAlert(error.message, 'error');
            }
        });
    }

    editNoteModal.style.display = 'block';
}

export async function deleteNote(id) {
    const confirmation = await confirmAction(`Are you sure you want to delete the note with ID: ${id}?`, 'Delete note?');
    if (!confirmation.isConfirmed) {
        return false;
    }

    try {
        const response = await fetch(`${API_URL}?id=${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        const result = normalizeResult(await readJsonResponse(response, `Failed to delete note with ID: ${id}`));

        if (!response.ok || result.status === 'error') {
            throw new Error(result.message || `Failed to delete note with ID: ${id}`);
        }

        showAlert(result.message || 'Note deleted successfully.', 'success');
        await index();
        return true;
    } catch (error) {
        console.error(error);
        showAlert(error.message, 'error');
        return false;
    }
}

export async function togglePin(id) {
    const button = document.querySelector(`button[data-action="toggle-pin"][data-id="${id}"]`);
    const currentPinned = button ? button.dataset.pinned === '1' : false;

    try {
        const response = await fetch(`${API_URL}?id=${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ pinned: !currentPinned })
        });

        const result = normalizeResult(await readJsonResponse(response, 'Failed to update pinned note.'));
        if (!response.ok || result.status === 'error') {
            throw new Error(result.message || 'Failed to update pin status.');
        }

        showAlert('Note pin updated.', 'success');
        await index();
        return true;
    } catch (error) {
        console.error(error);
        showAlert(error.message, 'error');
        return false;
    }
}

export function bindActions() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('button');

        if (!button) {
            return;
        }

        const action = button.dataset.action;
        const id = button.dataset.id;
        const page = button.dataset.page;

        if (page) {
            const searchInput = document.querySelector('#searchInput');
            const categoryFilter = document.querySelector('#categoryFilter');
            const searchValue = searchInput ? searchInput.value : '';
            const categoryValue = categoryFilter ? categoryFilter.value : '';
            await index(searchValue, Number(page), categoryValue);
            return;
        }

        if (action === 'add') {
            await addNote();
        }

        if (action === 'highlight-selection') {
            const editor = event.target.closest('.description-editor-wrap')?.querySelector('[data-role="description-editor"]');
            if (editor) {
                applyHighlightSelection(editor);
            }
            return;
        }

        if (action === 'remove-highlight') {
            const editor = event.target.closest('.description-editor-wrap')?.querySelector('[data-role="description-editor"]');
            if (editor) {
                removeHighlightSelection(editor);
            }
            return;
        }

        if (action === 'add-category') {
            await addCategory();
        }

        if (!action || !id) {
            return;
        }

        if (action === 'view') {
            await viewNote(id);
        }

        if (action === 'edit') {
            await editNote(id);
        }

        if (action === 'toggle-pin') {
            await togglePin(id);
        }

        if (action === 'delete') {
            await deleteNote(id);
        }
    });
}
