import { bindActions, index } from './api.js';

bindActions();

const searchForm = document.querySelector('#searchForm');
const searchInput = document.querySelector('#searchInput');
const clearSearchBtn = document.querySelector('#clearSearchBtn');

if (searchForm && searchInput) {
    searchForm.addEventListener('submit', (event) => {
        event.preventDefault();
        index(searchInput.value, 1);
    });
}

if (clearSearchBtn && searchInput) {
    clearSearchBtn.addEventListener('click', () => {
        searchInput.value = '';
        index('', 1);
    });
}

index('', 1);