"use strict";
const toggle = document.querySelector('[data-toggle-nav]');
const closeNavigation = () => { document.body.classList.remove('sidebar-open'); toggle?.setAttribute('aria-expanded', 'false'); };
toggle?.addEventListener('click', () => { const opened = document.body.classList.toggle('sidebar-open'); toggle.setAttribute('aria-expanded', String(opened)); });
document.querySelector('[data-close-nav]')?.addEventListener('click', closeNavigation);
document.addEventListener('keydown', event => { if (event.key === 'Escape') { closeNavigation(); } });
document.querySelectorAll('form[data-confirm]').forEach(form => { form.addEventListener('submit', event => { if (!window.confirm(form.dataset.confirm)) { event.preventDefault(); } }); });
let toastTimer;
function notify(message) { const toast = document.getElementById('toast'); if (!toast) return; toast.textContent = message; toast.classList.add('visible'); clearTimeout(toastTimer); toastTimer = setTimeout(() => toast.classList.remove('visible'), 5500); }
const board = document.querySelector('[data-board]');
if (board) {
    let draggedCard = null;
    function updateCounts() {
        board.querySelectorAll('.board-column').forEach(column => { const count = column.querySelectorAll('.pipeline-card').length; column.querySelector('.column-count').textContent = String(count); column.querySelector('.column-empty').classList.toggle('hidden', count > 0); });
    }
    async function moveCard(card, stage) {
        const previousColumn = card.closest('.board-column');
        const previousStage = previousColumn.dataset.stage;
        const selector = card.querySelector('[data-stage-select]');
        const targetColumn = Array.from(board.querySelectorAll('.board-column')).find(column => column.dataset.stage === stage);
        if (stage === previousStage || card.classList.contains('busy') || !targetColumn) { selector.value = previousStage; return; }
        card.classList.add('busy'); selector.disabled = true;
        try {
            const response = await fetch(card.dataset.url, { method: 'PATCH', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ status: stage, version: Number(card.dataset.version) }) });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) { const errors = Object.values(result.errors || {}).flat(); throw new Error(errors[0] || result.message || (response.status === 419 ? 'Your session expired. Refresh and sign in again.' : 'Unable to update the stage. Refresh and try again.')); }
            card.dataset.version = String(result.version);
            targetColumn.querySelector('.board-cards').append(card);
            selector.value = stage; updateCounts();
            const message = card.querySelector('h3').textContent + ' moved to ' + stage;
            board.querySelector('.board-live').textContent = message; notify(message);
        } catch (error) { selector.value = previousStage; notify(error.message); }
        finally { card.classList.remove('busy'); selector.disabled = false; selector.focus({ preventScroll: true }); }
    }
    board.querySelectorAll('.pipeline-card').forEach(card => {
        card.addEventListener('dragstart', event => { if (card.classList.contains('busy')) { event.preventDefault(); return; } draggedCard = card; card.classList.add('dragging'); event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', card.dataset.id); });
        card.addEventListener('dragend', () => { card.classList.remove('dragging'); draggedCard = null; board.querySelectorAll('.drag-over').forEach(column => column.classList.remove('drag-over')); });
        card.querySelector('[data-stage-select]').addEventListener('change', event => moveCard(card, event.target.value));
    });
    board.querySelectorAll('.board-column').forEach(column => {
        column.addEventListener('dragover', event => { if (draggedCard) { event.preventDefault(); event.dataTransfer.dropEffect = 'move'; column.classList.add('drag-over'); } });
        column.addEventListener('dragleave', event => { if (!column.contains(event.relatedTarget)) column.classList.remove('drag-over'); });
        column.addEventListener('drop', event => { event.preventDefault(); column.classList.remove('drag-over'); if (draggedCard) moveCard(draggedCard, column.dataset.stage); });
    });
}
