/**
 * POD Customizer Frontend Orchestrator
 * Manages Fabric.js canvas, layer state, interactive controls,
 * and serializes strict JSON data contract for WooCommerce Add to Cart.
 */
(function () {
  'use strict';

  // Ensure config and Fabric are available
  if (typeof podCustomizerConfig === 'undefined' || typeof fabric === 'undefined') {
    console.warn('POD Customizer: missing podCustomizerConfig or fabric.js.');
    return;
  }

  const config = podCustomizerConfig;
  const PREVIEW_SIZE = 600;
  const RENDER_SIZE = config.canvas?.width || 1200;
  const SCALE_RATIO = RENDER_SIZE / PREVIEW_SIZE; // e.g. 2.0

  // State
  let canvas = null;
  let mockupImg = null;
  let clipartObj = null;
  let textObj = null;
  let photoObj = null;

  let currentMockup = config.mockups[0] || null;
  let currentClipart = config.cliparts[0] || null;
  let currentFont = config.fonts[0] || 'Roboto';
  let currentColor = '#111827';
  let currentFontSize = 44;

  /**
   * DOM Elements
   */
  const elLoading = document.getElementById('pod-canvas-loading');
  const elMockupsGrid = document.getElementById('pod-mockups-grid');
  const elClipartGrid = document.getElementById('pod-clipart-grid');
  const elInputText = document.getElementById('pod-input-text');
  const elSelectFont = document.getElementById('pod-select-font');
  const elRangeSize = document.getElementById('pod-range-size');
  const elSizeVal = document.getElementById('pod-size-val');
  const elCustomColor = document.getElementById('pod-custom-color');
  const elFileInput = document.getElementById('pod-file-input');
  const elDropzone = document.getElementById('pod-upload-dropzone');
  const elUploadPreview = document.getElementById('pod-upload-preview');
  const elUploadName = document.getElementById('pod-upload-name');
  const elBtnRemovePhoto = document.getElementById('pod-btn-remove-photo');
  const elBtnReset = document.getElementById('pod-btn-reset');
  const elStateInput = document.getElementById('pod_canvas_state');
  const elPreviewInput = document.getElementById('pod_preview_image');
  const elAddToCartForm = document.querySelector('form.cart');

  /**
   * Initialize Studio
   */
  function init() {
    initCanvas();
    renderMockupGrid();
    renderClipartGrid();
    bindEvents();
    loadInitialLayers();
  }

  /**
   * Initialize Fabric Canvas
   */
  function initCanvas() {
    canvas = new fabric.Canvas('pod-live-canvas', {
      width: PREVIEW_SIZE,
      height: PREVIEW_SIZE,
      backgroundColor: '#f8fafc',
      selection: true,
      preserveObjectStacking: true,
    });

    // Handle user manipulation (drag, resize, rotate) on canvas
    canvas.on('object:modified', function () {
      syncStateToForm();
    });

    canvas.on('selection:created', onObjectSelected);
    canvas.on('selection:updated', onObjectSelected);
  }

  /**
   * Sync active selection back to control UI
   */
  function onObjectSelected(e) {
    const selected = e.selected?.[0];
    if (!selected) return;

    if (selected === textObj && elInputText) {
      elInputText.value = textObj.text || '';
    }
  }

  /**
   * Render Mockup Selection Grid
   */
  function renderMockupGrid() {
    if (!elMockupsGrid) return;
    elMockupsGrid.innerHTML = '';

    config.mockups.forEach((m, idx) => {
      const card = document.createElement('div');
      card.className = 'pod-mockup-card' + (idx === 0 ? ' active' : '');
      card.dataset.id = m.id;
      card.innerHTML = `
        <img src="${m.url}" alt="${m.name}" class="pod-mockup-thumb" />
        <span class="pod-mockup-title">${m.name}</span>
      `;
      card.addEventListener('click', () => selectMockup(m, card));
      elMockupsGrid.appendChild(card);
    });
  }

  /**
   * Render Clipart Selection Grid
   */
  function renderClipartGrid() {
    if (!elClipartGrid) return;
    elClipartGrid.innerHTML = '';

    config.cliparts.forEach((c, idx) => {
      const card = document.createElement('div');
      card.className = 'pod-clipart-card' + (idx === 0 ? ' active' : '');
      card.dataset.id = c.id;
      card.innerHTML = `
        <img src="${c.url}" alt="${c.name}" class="pod-clipart-thumb" />
        <span class="pod-clipart-title">${c.name}</span>
      `;
      card.addEventListener('click', () => selectClipart(c, card));
      elClipartGrid.appendChild(card);
    });
  }

  /**
   * Select Mockup Base Image
   */
  function selectMockup(m, cardEl) {
    currentMockup = m;
    document.querySelectorAll('.pod-mockup-card').forEach((el) => el.classList.remove('active'));
    if (cardEl) cardEl.classList.add('active');

    loadMockupImage(m.url);
  }

  /**
   * Select Clipart
   */
  function selectClipart(c, cardEl) {
    currentClipart = c;
    document.querySelectorAll('.pod-clipart-card').forEach((el) => el.classList.remove('active'));
    if (cardEl) cardEl.classList.add('active');

    loadClipartImage(c.url);
  }

  /**
   * Load Mockup Image Layer
   */
  function loadMockupImage(url) {
    fabric.Image.fromURL(
      url,
      function (img) {
        if (!img) return;

        if (mockupImg) {
          canvas.remove(mockupImg);
        }

        mockupImg = img;
        mockupImg.set({
          id: 'mockup_base',
          name: currentMockup ? currentMockup.name : 'Product Base',
          originX: 'center',
          originY: 'center',
          left: PREVIEW_SIZE / 2,
          top: PREVIEW_SIZE / 2,
          selectable: false,
          evented: false,
          printable: false,
        });

        // Fit to preview canvas
        mockupImg.scaleToWidth(PREVIEW_SIZE);
        canvas.add(mockupImg);
        mockupImg.sendToBack();
        canvas.renderAll();
        syncStateToForm();
      },
      { crossOrigin: 'anonymous' }
    );
  }

  /**
   * Load Clipart Layer
   */
  function loadClipartImage(url) {
    fabric.Image.fromURL(
      url,
      function (img) {
        if (!img) return;

        if (clipartObj) {
          canvas.remove(clipartObj);
        }

        clipartObj = img;
        clipartObj.set({
          id: 'clipart_1',
          name: currentClipart ? currentClipart.name : 'Selected Clipart',
          originX: 'center',
          originY: 'center',
          left: PREVIEW_SIZE / 2,
          top: PREVIEW_SIZE / 2 - 50,
          selectable: true,
          printable: true,
          cornerColor: '#4f46e5',
          cornerStrokeColor: '#ffffff',
          cornerSize: 9,
          transparentCorners: false,
        });

        clipartObj.scaleToWidth(180);
        canvas.add(clipartObj);
        bringDecorationsToFront();
        canvas.setActiveObject(clipartObj);
        canvas.renderAll();
        syncStateToForm();
      },
      { crossOrigin: 'anonymous' }
    );
  }

  /**
   * Initialize / Update Text Layer
   */
  function updateTextLayer(content) {
    const textString = content !== undefined ? content : (elInputText ? elInputText.value : 'Best Dad Ever');

    if (!textObj) {
      textObj = new fabric.Text(textString, {
        id: 'custom_text_1',
        name: 'Custom Title',
        originX: 'center',
        originY: 'center',
        left: PREVIEW_SIZE / 2,
        top: PREVIEW_SIZE / 2 + 100,
        fontFamily: currentFont,
        fontSize: currentFontSize,
        fill: currentColor,
        textAlign: 'center',
        selectable: true,
        printable: true,
        cornerColor: '#4f46e5',
        cornerStrokeColor: '#ffffff',
        cornerSize: 9,
        transparentCorners: false,
      });

      canvas.add(textObj);
    } else {
      textObj.set({
        text: textString,
        fontFamily: currentFont,
        fontSize: currentFontSize,
        fill: currentColor,
      });
    }

    bringDecorationsToFront();
    canvas.renderAll();
    syncStateToForm();
  }

  /**
   * Load Initial Layers
   */
  function loadInitialLayers() {
    if (elLoading) elLoading.style.display = 'flex';

    if (currentMockup) {
      loadMockupImage(currentMockup.url);
    }

    if (currentClipart) {
      loadClipartImage(currentClipart.url);
    }

    updateTextLayer(elInputText ? elInputText.value : 'Best Dad Ever');

    setTimeout(() => {
      if (elLoading) {
        elLoading.style.opacity = '0';
        setTimeout(() => (elLoading.style.display = 'none'), 300);
      }
    }, 400);
  }

  /**
   * Ensure layers are ordered correctly
   */
  function bringDecorationsToFront() {
    if (mockupImg) mockupImg.sendToBack();
    if (clipartObj) canvas.bringForward(clipartObj);
    if (photoObj) canvas.bringForward(photoObj);
    if (textObj) canvas.bringToFront(textObj);
  }

  /**
   * Bind DOM Events
   */
  function bindEvents() {
    // 1. Navigation Tabs
    const tabBtns = document.querySelectorAll('.pod-tab-btn');
    tabBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        tabBtns.forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');

        const targetId = btn.dataset.tab;
        document.querySelectorAll('.pod-tab-pane').forEach((p) => p.classList.remove('active'));
        const targetPane = document.getElementById(targetId);
        if (targetPane) targetPane.classList.add('active');
      });
    });

    // 2. Text Input
    if (elInputText) {
      elInputText.addEventListener('input', (e) => {
        updateTextLayer(e.target.value);
      });
    }

    // 3. Font Family Selector
    if (elSelectFont) {
      elSelectFont.addEventListener('change', (e) => {
        currentFont = e.target.value;
        updateTextLayer();
      });
    }

    // 4. Font Size Range
    if (elRangeSize) {
      elRangeSize.addEventListener('input', (e) => {
        currentFontSize = parseInt(e.target.value, 10);
        if (elSizeVal) elSizeVal.textContent = currentFontSize + 'px';
        updateTextLayer();
      });
    }

    // 5. Text Color Presets
    document.querySelectorAll('#pod-text-colors .pod-color-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('#pod-text-colors .pod-color-btn').forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        currentColor = btn.dataset.color;
        if (elCustomColor) elCustomColor.value = currentColor;
        updateTextLayer();
      });
    });

    if (elCustomColor) {
      elCustomColor.addEventListener('input', (e) => {
        currentColor = e.target.value;
        updateTextLayer();
      });
    }

    // 6. Photo Upload
    if (elDropzone && elFileInput) {
      elDropzone.addEventListener('click', () => elFileInput.click());
      elDropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        elDropzone.classList.add('dragover');
      });
      elDropzone.addEventListener('dragleave', () => elDropzone.classList.remove('dragover'));
      elDropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        elDropzone.classList.remove('dragover');
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
          handleFileUpload(e.dataTransfer.files[0]);
        }
      });

      elFileInput.addEventListener('change', (e) => {
        if (e.target.files && e.target.files[0]) {
          handleFileUpload(e.target.files[0]);
        }
      });
    }

    if (elBtnRemovePhoto) {
      elBtnRemovePhoto.addEventListener('click', () => {
        if (photoObj) {
          canvas.remove(photoObj);
          photoObj = null;
          if (elUploadPreview) elUploadPreview.style.display = 'none';
          if (elDropzone) elDropzone.style.display = 'flex';
          if (elFileInput) elFileInput.value = '';
          canvas.renderAll();
          syncStateToForm();
        }
      });
    }

    // 7. Reset Button
    if (elBtnReset) {
      elBtnReset.addEventListener('click', resetStudio);
    }

    // 8. Add to Cart Form Submission Interceptor
    if (elAddToCartForm) {
      elAddToCartForm.addEventListener('submit', onAddToCartSubmit);
    }
  }

  /**
   * Handle Photo File Upload
   */
  function handleFileUpload(file) {
    if (!file.type.match(/^image\/(png|jpeg|webp)$/)) {
      alert(config.i18n.invalidPhoto);
      return;
    }

    const reader = new FileReader();
    reader.onload = function (f) {
      const dataUrl = f.target.result;
      fabric.Image.fromURL(dataUrl, function (img) {
        if (!img) return;

        if (photoObj) {
          canvas.remove(photoObj);
        }

        photoObj = img;
        photoObj.set({
          id: 'user_photo_1',
          name: file.name,
          originX: 'center',
          originY: 'center',
          left: PREVIEW_SIZE / 2,
          top: PREVIEW_SIZE / 2 - 30,
          selectable: true,
          printable: true,
          cornerColor: '#06b6d4',
          cornerStrokeColor: '#ffffff',
          cornerSize: 9,
          transparentCorners: false,
        });

        photoObj.scaleToWidth(160);
        canvas.add(photoObj);
        bringDecorationsToFront();
        canvas.setActiveObject(photoObj);
        canvas.renderAll();

        if (elDropzone) elDropzone.style.display = 'none';
        if (elUploadPreview) elUploadPreview.style.display = 'flex';
        if (elUploadName) elUploadName.textContent = file.name;

        syncStateToForm();
      });
    };
    reader.readAsDataURL(file);
  }

  /**
   * Reset Studio to default
   */
  function resetStudio() {
    if (elInputText) elInputText.value = 'Best Dad Ever';
    currentFontSize = 44;
    if (elRangeSize) elRangeSize.value = 44;
    if (elSizeVal) elSizeVal.textContent = '44px';
    currentFont = 'Roboto';
    if (elSelectFont) elSelectFont.value = 'Roboto';
    currentColor = '#111827';
    if (elCustomColor) elCustomColor.value = currentColor;

    if (photoObj) {
      canvas.remove(photoObj);
      photoObj = null;
      if (elUploadPreview) elUploadPreview.style.display = 'none';
      if (elDropzone) elDropzone.style.display = 'flex';
      if (elFileInput) elFileInput.value = '';
    }

    if (config.mockups[0]) {
      selectMockup(config.mockups[0], document.querySelector('.pod-mockup-card'));
    }
    if (config.cliparts[0]) {
      selectClipart(config.cliparts[0], document.querySelector('.pod-clipart-card'));
    }

    updateTextLayer('Best Dad Ever');
  }

  /**
   * Serialize Strict JSON Data Contract
   * Scales coordinates from 600x600 preview to 1200x1200 render resolution.
   */
  function serializeCanvasState() {
    const layers = [];

    // 1. Mockup Background Layer
    if (mockupImg) {
      layers.push({
        id: 'mockup_base',
        type: 'image',
        name: currentMockup ? currentMockup.name : 'Product Base',
        url: currentMockup ? currentMockup.url : '',
        x: Math.round(mockupImg.left * SCALE_RATIO),
        y: Math.round(mockupImg.top * SCALE_RATIO),
        width: Math.round(mockupImg.getScaledWidth() * SCALE_RATIO),
        height: Math.round(mockupImg.getScaledHeight() * SCALE_RATIO),
        rotation: Math.round(mockupImg.angle || 0),
        zIndex: 1,
        printable: false,
      });
    }

    // 2. Clipart Layer
    if (clipartObj) {
      layers.push({
        id: 'clipart_1',
        type: 'image',
        name: currentClipart ? currentClipart.name : 'Selected Clipart',
        url: currentClipart ? currentClipart.url : '',
        x: Math.round(clipartObj.left * SCALE_RATIO),
        y: Math.round(clipartObj.top * SCALE_RATIO),
        width: Math.round(clipartObj.getScaledWidth() * SCALE_RATIO),
        height: Math.round(clipartObj.getScaledHeight() * SCALE_RATIO),
        rotation: Math.round(clipartObj.angle || 0),
        zIndex: 2,
        printable: true,
      });
    }

    // 3. User Uploaded Photo (if any)
    if (photoObj) {
      layers.push({
        id: 'user_photo_1',
        type: 'image',
        name: photoObj.name || 'User Photo',
        url: photoObj.toDataURL({ format: 'png' }),
        x: Math.round(photoObj.left * SCALE_RATIO),
        y: Math.round(photoObj.top * SCALE_RATIO),
        width: Math.round(photoObj.getScaledWidth() * SCALE_RATIO),
        height: Math.round(photoObj.getScaledHeight() * SCALE_RATIO),
        rotation: Math.round(photoObj.angle || 0),
        zIndex: 3,
        printable: true,
      });
    }

    // 4. Custom Text Layer
    if (textObj) {
      layers.push({
        id: 'custom_text_1',
        type: 'text',
        name: 'Custom Title',
        text: textObj.text || '',
        fontFamily: textObj.fontFamily || 'Roboto',
        fontSize: Math.round((textObj.fontSize || 44) * (textObj.scaleY || 1) * SCALE_RATIO),
        fill: textObj.fill || '#111827',
        textAlign: textObj.textAlign || 'center',
        x: Math.round(textObj.left * SCALE_RATIO),
        y: Math.round(textObj.top * SCALE_RATIO),
        rotation: Math.round(textObj.angle || 0),
        zIndex: 4,
        printable: true,
      });
    }

    return {
      version: '1.0',
      canvas: {
        width: RENDER_SIZE,
        height: RENDER_SIZE,
        dpi: 300,
        unit: 'px',
      },
      preview: {
        width: PREVIEW_SIZE,
        height: PREVIEW_SIZE,
      },
      layers: layers,
    };
  }

  /**
   * Sync serialized JSON state to form hidden fields
   */
  function syncStateToForm() {
    if (!elStateInput || !canvas) return;

    const payload = serializeCanvasState();
    elStateInput.value = JSON.stringify(payload);

    // Generate small thumbnail preview image
    if (elPreviewInput) {
      try {
        const thumbUrl = canvas.toDataURL({
          format: 'jpeg',
          quality: 0.7,
          multiplier: 0.5,
        });
        elPreviewInput.value = thumbUrl;
      } catch (err) {
        // SVG cross-origin canvas security restriction fallback
      }
    }
  }

  /**
   * Validate state on Add to Cart submission
   */
  function onAddToCartSubmit(e) {
    if (!elStateInput) return;

    syncStateToForm();

    const textValue = elInputText ? elInputText.value.trim() : '';
    if (!textValue) {
      e.preventDefault();
      alert(config.i18n.emptyTextAlert);
      if (elInputText) {
        elInputText.focus();
        elInputText.style.borderColor = '#ef4444';
      }
      return false;
    }

    if (!elStateInput.value) {
      e.preventDefault();
      alert('Design state could not be serialized.');
      return false;
    }
  }

  // Run when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
