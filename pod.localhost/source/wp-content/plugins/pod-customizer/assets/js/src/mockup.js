/**
 * POD Customizer: Mockup Selection & Background Manager
 */

import { state, elements, config, fabric, PREVIEW_SIZE } from './state.js';
import { syncStateToForm } from './serializer.js';

export function renderMockupGrid() {
  if (!elements.mockupsGrid || !config.mockups) return;
  elements.mockupsGrid.innerHTML = '';

  config.mockups.forEach((m, idx) => {
    const card = document.createElement('div');
    card.className = 'pod-mockup-card' + (idx === 0 ? ' active' : '');
    card.dataset.id = m.id;
    card.innerHTML = `
      <img src="${m.url}" alt="${m.name}" class="pod-mockup-thumb" />
      <span class="pod-mockup-title">${m.name}</span>
    `;
    card.addEventListener('click', () => selectMockup(m, card));
    elements.mockupsGrid.appendChild(card);
  });
}

export function selectMockup(mockup, activeCard) {
  state.currentMockup = mockup;
  document.querySelectorAll('.pod-mockup-card').forEach((c) => c.classList.remove('active'));
  if (activeCard) activeCard.classList.add('active');
  loadMockupImage(mockup.url);
}

export function loadMockupImage(url) {
  if (!fabric || !state.canvas) return;

  fabric.Image.fromURL(
    url,
    function (img) {
      if (!img) return;

      if (state.mockupImg) {
        state.canvas.remove(state.mockupImg);
      }

      state.mockupImg = img;
      state.mockupImg.set({
        podType: 'mockup',
        podId: 'mockup_base',
        originX: 'center',
        originY: 'center',
        left: PREVIEW_SIZE / 2,
        top: PREVIEW_SIZE / 2,
        selectable: false,
        evented: false,
        printable: false,
      });

      state.mockupImg.scaleToWidth(PREVIEW_SIZE * 0.94);
      state.canvas.add(state.mockupImg);
      state.mockupImg.sendToBack();
      state.canvas.renderAll();
      syncStateToForm();
    },
    { crossOrigin: 'anonymous' }
  );
}
