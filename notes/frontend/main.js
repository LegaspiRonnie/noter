import { bindActions, index } from './api.js';

bindActions();

const searchForm = document.querySelector('#searchForm');
const searchInput = document.querySelector('#searchInput');
const clearSearchBtn = document.querySelector('#clearSearchBtn');

if (searchForm && searchInput) {
    searchForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const categoryFilter = document.querySelector('#categoryFilter');
        index(searchInput.value, 1, categoryFilter ? categoryFilter.value : '');
    });
}

if (clearSearchBtn && searchInput) {
    clearSearchBtn.addEventListener('click', () => {
        searchInput.value = '';
        const categoryFilter = document.querySelector('#categoryFilter');
        if (categoryFilter) {
            categoryFilter.value = '';
        }
        index('', 1, '');
    });
}

const categoryFilter = document.querySelector('#categoryFilter');
if (categoryFilter) {
    categoryFilter.addEventListener('change', () => {
        const searchInput = document.querySelector('#searchInput');
        index(searchInput ? searchInput.value : '', 1, categoryFilter.value);
    });
}

index('', 1, '');