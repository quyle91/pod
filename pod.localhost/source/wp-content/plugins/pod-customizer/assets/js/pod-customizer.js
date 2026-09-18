(() => {
  // assets/js/src/state.js
  var config = window.podCustomizerConfig || {};
  var fabric = window.fabric;
  if (fabric) {
    if (fabric.Object) fabric.Object.prototype.textBaseline = "alphabetic";
    if (fabric.Text) fabric.Text.prototype.textBaseline = "alphabetic";
    if (fabric.IText) fabric.IText.prototype.textBaseline = "alphabetic";
    if (fabric.Textbox) fabric.Textbox.prototype.textBaseline = "alphabetic";
  }
  var template = config.template || null;
  var isTemplateMode = !!template;
  var templateValues = {};
  var templateMetrics = {};
  if (template && Array.isArray(template.fields)) {
    template.fields.forEach((f) => {
      templateValues[f.id] = f.default_value;
    });
  }
  var PREVIEW_SIZE = 600;
  var RENDER_SIZE = template?.print_spec?.width_px || config.canvas?.width || 1200;
  var SCALE_RATIO = RENDER_SIZE / PREVIEW_SIZE;
  var state = {
    canvas: null,
    mockupImg: null,
    currentMockup: template?.mockup || config.mockups?.[0] || null,
    templateValues,
    templateMetrics,
    templateObjects: {},
    // Map of field_id -> fabric object or array of objects
    editingTextObj: null,
    editingClipartObj: null,
    editingPhotoObj: null
  };
  var elements = {
    loading: document.getElementById("pod-canvas-loading"),
    mockupsGrid: document.getElementById("pod-mockups-grid"),
    clipartGrid: document.getElementById("pod-clipart-grid"),
    btnAddText: document.getElementById("pod-btn-add-text"),
    textList: document.getElementById("pod-text-list"),
    btnAddClipart: document.getElementById("pod-btn-add-clipart"),
    clipartList: document.getElementById("pod-clipart-list"),
    btnAddPhoto: document.getElementById("pod-btn-add-photo"),
    photoList: document.getElementById("pod-photo-list"),
    // Text Modal
    textModal: document.getElementById("pod-text-modal"),
    textModalTitle: document.getElementById("pod-modal-title"),
    textModalBtnClose: document.getElementById("pod-modal-btn-close"),
    textModalBtnDone: document.getElementById("pod-modal-btn-done"),
    modalInputText: document.getElementById("pod-modal-input-text"),
    modalSelectFont: document.getElementById("pod-modal-select-font"),
    modalRangeSize: document.getElementById("pod-modal-range-size"),
    modalSizeVal: document.getElementById("pod-modal-size-val"),
    modalCustomColor: document.getElementById("pod-modal-custom-color"),
    modalColorsContainer: document.getElementById("pod-modal-text-colors"),
    // Clipart Modal
    clipartModal: document.getElementById("pod-clipart-modal"),
    clipartModalTitle: document.getElementById("pod-clipart-modal-title"),
    clipartModalBtnClose: document.getElementById("pod-clipart-modal-btn-close"),
    // Photo Modal
    photoModal: document.getElementById("pod-photo-modal"),
    photoModalTitle: document.getElementById("pod-photo-modal-title"),
    photoModalBtnClose: document.getElementById("pod-photo-modal-btn-close"),
    fileInput: document.getElementById("pod-file-input"),
    dropzone: document.getElementById("pod-upload-dropzone"),
    // Preview Modal & Buttons
    btnPreview: document.getElementById("pod-btn-preview"),
    btnQuickPreview: document.getElementById("pod-btn-quick-preview"),
    previewModal: document.getElementById("pod-preview-modal"),
    previewModalImg: document.getElementById("pod-preview-modal-img"),
    previewModalSpecs: document.getElementById("pod-preview-specs"),
    previewModalBtnClose: document.getElementById("pod-preview-modal-btn-close"),
    previewModalBtnDone: document.getElementById("pod-preview-modal-btn-done"),
    // Form & Reset
    btnReset: document.getElementById("pod-btn-reset"),
    stateInput: document.getElementById("pod_canvas_state"),
    previewInput: document.getElementById("pod_preview_image"),
    addToCartForm: document.querySelector("form.cart")
  };

  // assets/js/src/utils.js
  function escapeHtml(str) {
    if (!str) return "";
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  }
  function getLayersByType(type) {
    if (!state.canvas) return [];
    return state.canvas.getObjects().filter((obj) => obj.podType === type);
  }
  function highlightActiveLayerItem(selected) {
    document.querySelectorAll(".pod-layer-item").forEach((item) => {
      if (selected && item.dataset.podId === selected.podId) {
        item.classList.add("active");
      } else {
        item.classList.remove("active");
      }
    });
  }
  function bringDecorationsToFront() {
    if (state.mockupImg) state.mockupImg.sendToBack();
    const cliparts = getLayersByType("clipart");
    const photos = getLayersByType("photo");
    const texts = getLayersByType("text");
    cliparts.forEach((c) => state.canvas.bringForward(c));
    photos.forEach((p) => state.canvas.bringForward(p));
    texts.forEach((t2) => state.canvas.bringToFront(t2));
  }
  function t(key, fallback = "") {
    if (window.podCustomizerI18n && window.podCustomizerI18n[key] !== void 0) {
      return window.podCustomizerI18n[key];
    }
    if (window.podCustomizerConfig && window.podCustomizerConfig.i18n && window.podCustomizerConfig.i18n[key] !== void 0) {
      return window.podCustomizerConfig.i18n[key];
    }
    if (window.wp && window.wp.i18n && typeof window.wp.i18n.__ === "function") {
      return window.wp.i18n.__(fallback || key, "pod-customizer");
    }
    return fallback;
  }
  function debounce(func, wait = 100) {
    let timeout;
    return function(...args) {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(this, args), wait);
    };
  }

  // assets/js/src/serializer.js
  function serializeCanvasState() {
    const layers = [];
    if (state.mockupImg) {
      layers.push({
        id: "mockup_base",
        type: "image",
        name: state.currentMockup ? state.currentMockup.name : "Product Base",
        url: state.currentMockup ? state.currentMockup.url : "",
        x: Math.round(state.mockupImg.left * SCALE_RATIO),
        y: Math.round(state.mockupImg.top * SCALE_RATIO),
        width: Math.round(state.mockupImg.getScaledWidth() * SCALE_RATIO),
        height: Math.round(state.mockupImg.getScaledHeight() * SCALE_RATIO),
        rotation: Math.round(state.mockupImg.angle || 0),
        zIndex: 1,
        printable: false
      });
    }
    if (state.canvas) {
      const objects = state.canvas.getObjects();
      let zIdx = 2;
      objects.forEach((obj) => {
        if (obj === state.mockupImg) return;
        if (obj.podType === "clipart") {
          layers.push({
            id: obj.podId || `clipart_${zIdx}`,
            type: "image",
            name: obj.clipartName || "Clipart",
            url: obj.clipartUrl || "",
            x: Math.round(obj.left * SCALE_RATIO),
            y: Math.round(obj.top * SCALE_RATIO),
            width: Math.round(obj.getScaledWidth() * SCALE_RATIO),
            height: Math.round(obj.getScaledHeight() * SCALE_RATIO),
            rotation: Math.round(obj.angle || 0),
            zIndex: zIdx++,
            printable: true
          });
        } else if (obj.podType === "photo") {
          layers.push({
            id: obj.podId || `photo_${zIdx}`,
            type: "image",
            name: obj.photoName || "User Photo",
            url: obj.toDataURL ? obj.toDataURL({ format: "png" }) : "",
            x: Math.round(obj.left * SCALE_RATIO),
            y: Math.round(obj.top * SCALE_RATIO),
            width: Math.round(obj.getScaledWidth() * SCALE_RATIO),
            height: Math.round(obj.getScaledHeight() * SCALE_RATIO),
            rotation: Math.round(obj.angle || 0),
            zIndex: zIdx++,
            printable: true
          });
        } else if (obj.podType === "text") {
          layers.push({
            id: obj.podId || `text_${zIdx}`,
            type: "text",
            name: obj.text || "Custom Text",
            text: obj.text || "",
            fontFamily: obj.fontFamily || "Roboto",
            fontSize: Math.round((obj.fontSize || 44) * (obj.scaleY || 1) * SCALE_RATIO),
            fontWeight: obj.fontWeight || "normal",
            fontStyle: obj.fontStyle || "normal",
            fill: obj.fill || "#111827",
            textAlign: obj.textAlign || "center",
            x: Math.round(obj.left * SCALE_RATIO),
            y: Math.round(obj.top * SCALE_RATIO),
            width: Math.round(obj.getScaledWidth() * SCALE_RATIO),
            height: Math.round(obj.getScaledHeight() * SCALE_RATIO),
            rotation: Math.round(obj.angle || 0),
            zIndex: zIdx++,
            printable: true
          });
        } else if (obj.podType === "template_layer" || obj.podType === "template_fixed") {
          if (obj.visible !== false) {
            layers.push({
              id: obj.podLayerId || `tpl_${zIdx}`,
              type: "image",
              name: obj.podLayerName || "Template Layer",
              url: obj.podLayerUrl || "",
              x: Math.round(obj.left * SCALE_RATIO),
              y: Math.round(obj.top * SCALE_RATIO),
              width: Math.round(obj.getScaledWidth() * SCALE_RATIO),
              height: Math.round(obj.getScaledHeight() * SCALE_RATIO),
              rotation: Math.round(obj.angle || 0),
              zIndex: obj.podZIndex || zIdx++,
              printable: true
            });
          }
        }
      });
    }
    const serialized = {
      version: "1.0",
      canvas: {
        width: RENDER_SIZE,
        height: RENDER_SIZE,
        dpi: 300,
        unit: "px"
      },
      preview: {
        width: PREVIEW_SIZE,
        height: PREVIEW_SIZE
      },
      layers
    };
    if (state.templateValues) {
      serialized.template_payload = {
        template_id: template?.id,
        values: state.templateValues,
        metrics: state.templateMetrics || {}
      };
    }
    return serialized;
  }
  function syncStateToForm() {
    if (!elements.stateInput || !state.canvas) return;
    const payload = serializeCanvasState();
    elements.stateInput.value = JSON.stringify(payload);
    if (elements.previewInput) {
      try {
        const thumbUrl = state.canvas.toDataURL({
          format: "jpeg",
          quality: 0.7,
          multiplier: 0.5
        });
        elements.previewInput.value = thumbUrl;
      } catch (err) {
      }
    }
  }

  // assets/js/src/text.js
  function addTextLayer(initialText, openModalNow = true) {
    if (!fabric || !state.canvas) return null;
    const textString = initialText || t("defaultText", "Your Custom Text");
    const count = getLayersByType("text").length;
    const offset = count * 35;
    const textObj = new fabric.Text(textString, {
      podType: "text",
      podId: "text_" + Date.now() + "_" + Math.floor(Math.random() * 1e3),
      originX: "center",
      originY: "center",
      left: PREVIEW_SIZE / 2,
      top: PREVIEW_SIZE / 2 + 80 + offset,
      fontFamily: "Roboto",
      fontSize: 44,
      fill: "#111827",
      textAlign: "center",
      selectable: true,
      printable: true,
      cornerColor: "#4f46e5",
      cornerStrokeColor: "#ffffff",
      cornerSize: 9,
      transparentCorners: false,
      textBaseline: "alphabetic"
    });
    state.canvas.add(textObj);
    bringDecorationsToFront();
    state.canvas.setActiveObject(textObj);
    state.canvas.renderAll();
    renderTextList();
    syncStateToForm();
    if (openModalNow) {
      openTextModal(textObj);
    }
    return textObj;
  }
  function renderTextList() {
    if (!elements.textList) return;
    elements.textList.innerHTML = "";
    const textLayers = getLayersByType("text");
    if (textLayers.length === 0) {
      elements.textList.innerHTML = `
      <div class="pod-empty-state">
        <span>${t("emptyTextList", 'No custom text lines added yet. Click "Add Text" to create one.')}</span>
      </div>
    `;
      return;
    }
    const activeObj = state.canvas ? state.canvas.getActiveObject() : null;
    textLayers.forEach((obj) => {
      const item = document.createElement("div");
      item.className = "pod-layer-item" + (activeObj === obj ? " active" : "");
      item.dataset.podId = obj.podId;
      item.innerHTML = `
      <div class="pod-layer-info">
        <span class="pod-layer-color-dot" style="background-color: ${obj.fill};"></span>
        <div class="pod-layer-texts">
          <span class="pod-layer-title">${escapeHtml(obj.text || "(Empty)")}</span>
          <span class="pod-layer-subtitle">${escapeHtml(obj.fontFamily)} \u2022 ${Math.round(obj.fontSize * (obj.scaleY || 1))}px</span>
        </div>
      </div>
      <div class="pod-layer-actions">
        <button type="button" class="pod-btn-edit" title="${escapeHtml(t("titleEditText", "Edit text"))}">\u270F\uFE0F ${escapeHtml(t("btnEdit", "Edit"))}</button>
        <button type="button" class="pod-btn-delete" title="${escapeHtml(t("titleDeleteText", "Delete text"))}">\u{1F5D1}\uFE0F ${escapeHtml(t("btnDelete", "Delete"))}</button>
      </div>
    `;
      item.addEventListener("click", (e) => {
        if (e.target.closest("button")) return;
        if (state.canvas) {
          state.canvas.setActiveObject(obj);
          state.canvas.renderAll();
        }
        highlightActiveLayerItem(obj);
        openTextModal(obj);
      });
      item.querySelector(".pod-btn-edit").addEventListener("click", (e) => {
        e.stopPropagation();
        openTextModal(obj);
      });
      item.querySelector(".pod-btn-delete").addEventListener("click", (e) => {
        e.stopPropagation();
        if (state.canvas) {
          state.canvas.remove(obj);
          state.canvas.renderAll();
        }
        renderTextList();
        syncStateToForm();
      });
      elements.textList.appendChild(item);
    });
  }
  function openTextModal(textObj) {
    if (!elements.textModal || !textObj) return;
    state.editingTextObj = textObj;
    if (elements.textModalTitle) {
      elements.textModalTitle.textContent = t("modalEditText", "Edit Text");
    }
    if (state.canvas) {
      state.canvas.setActiveObject(textObj);
      state.canvas.renderAll();
    }
    if (elements.textModalTitle) {
      elements.textModalTitle.textContent = "Edit Text";
    }
    if (elements.modalInputText) {
      elements.modalInputText.value = textObj.text || "";
    }
    if (elements.modalSelectFont) {
      elements.modalSelectFont.value = textObj.fontFamily || "Roboto";
    }
    const effectiveSize = Math.round((textObj.fontSize || 44) * (textObj.scaleY || 1));
    if (elements.modalRangeSize) {
      elements.modalRangeSize.value = effectiveSize;
    }
    if (elements.modalSizeVal) {
      elements.modalSizeVal.textContent = effectiveSize + "px";
    }
    updateModalColorSelection(textObj.fill || "#111827");
    elements.textModal.style.display = "flex";
    if (elements.modalInputText) {
      elements.modalInputText.focus();
    }
  }
  function updateModalColorSelection(color) {
    if (!elements.modalColorsContainer) return;
    const btns = elements.modalColorsContainer.querySelectorAll(".pod-color-btn");
    btns.forEach((btn) => {
      if (btn.dataset.color.toLowerCase() === color.toLowerCase()) {
        btn.classList.add("active");
      } else {
        btn.classList.remove("active");
      }
    });
    if (elements.modalCustomColor) {
      elements.modalCustomColor.value = color.startsWith("#") && color.length === 7 ? color : "#111827";
    }
  }
  function closeTextModal() {
    if (!elements.textModal) return;
    elements.textModal.style.display = "none";
    state.editingTextObj = null;
    renderTextList();
    syncStateToForm();
  }
  function initTextEvents() {
    if (elements.btnAddText) {
      elements.btnAddText.addEventListener("click", () => {
        addTextLayer("Your Custom Text", true);
      });
    }
    if (elements.textModalBtnClose) {
      elements.textModalBtnClose.addEventListener("click", closeTextModal);
    }
    if (elements.textModalBtnDone) {
      elements.textModalBtnDone.addEventListener("click", closeTextModal);
    }
    if (elements.textModal) {
      elements.textModal.addEventListener("click", (e) => {
        if (e.target === elements.textModal) closeTextModal();
      });
    }
    if (elements.modalInputText) {
      elements.modalInputText.addEventListener("input", (e) => {
        if (!state.editingTextObj) return;
        state.editingTextObj.set({ text: e.target.value });
        if (state.canvas) state.canvas.renderAll();
        renderTextList();
      });
    }
    if (elements.modalSelectFont) {
      elements.modalSelectFont.addEventListener("change", (e) => {
        if (!state.editingTextObj) return;
        state.editingTextObj.set({ fontFamily: e.target.value });
        if (state.canvas) state.canvas.renderAll();
        renderTextList();
      });
    }
    if (elements.modalRangeSize) {
      elements.modalRangeSize.addEventListener("input", (e) => {
        if (!state.editingTextObj) return;
        const size = parseInt(e.target.value, 10);
        state.editingTextObj.set({ fontSize: size, scaleX: 1, scaleY: 1 });
        if (elements.modalSizeVal) elements.modalSizeVal.textContent = size + "px";
        if (state.canvas) state.canvas.renderAll();
        renderTextList();
      });
    }
    if (elements.modalColorsContainer) {
      const colorBtns = elements.modalColorsContainer.querySelectorAll(".pod-color-btn");
      colorBtns.forEach((btn) => {
        btn.addEventListener("click", () => {
          if (!state.editingTextObj) return;
          const color = btn.dataset.color;
          state.editingTextObj.set({ fill: color });
          updateModalColorSelection(color);
          if (state.canvas) state.canvas.renderAll();
          renderTextList();
        });
      });
    }
    if (elements.modalCustomColor) {
      elements.modalCustomColor.addEventListener("input", (e) => {
        if (!state.editingTextObj) return;
        const color = e.target.value;
        state.editingTextObj.set({ fill: color });
        updateModalColorSelection(color);
        if (state.canvas) state.canvas.renderAll();
        renderTextList();
      });
    }
  }

  // assets/js/src/clipart.js
  function renderClipartGrid() {
    if (!elements.clipartGrid || !config.cliparts) return;
    elements.clipartGrid.innerHTML = "";
    config.cliparts.forEach((c) => {
      const card = document.createElement("div");
      card.className = "pod-clipart-card";
      card.dataset.id = c.id;
      card.innerHTML = `
      <img src="${c.url}" alt="${c.name}" class="pod-clipart-thumb" />
      <span class="pod-clipart-title">${c.name}</span>
    `;
      card.addEventListener("click", () => onSelectClipartFromModal(c));
      elements.clipartGrid.appendChild(card);
    });
  }
  function onSelectClipartFromModal(c) {
    if (state.editingClipartObj) {
      replaceClipartLayer(state.editingClipartObj, c);
    } else {
      addClipartLayer(c);
    }
    closeClipartModal();
  }
  function openClipartModal(targetObj = null) {
    state.editingClipartObj = targetObj;
    if (elements.clipartModalTitle) {
      elements.clipartModalTitle.textContent = targetObj ? t("modalChangeClipart", "Change Clipart Graphic") : t("modalChooseClipart", "Choose Clipart Graphic");
    }
    if (elements.clipartModal) {
      elements.clipartModal.style.display = "flex";
    }
  }
  function closeClipartModal() {
    state.editingClipartObj = null;
    if (elements.clipartModal) {
      elements.clipartModal.style.display = "none";
    }
  }
  function addClipartLayer(clipartItem) {
    if (!fabric || !state.canvas) return;
    fabric.Image.fromURL(
      clipartItem.url,
      function(img) {
        if (!img) return;
        const count = getLayersByType("clipart").length;
        const offset = count % 4 * 20;
        img.set({
          podType: "clipart",
          podId: "clipart_" + Date.now() + "_" + Math.floor(Math.random() * 1e3),
          clipartName: clipartItem.name,
          clipartUrl: clipartItem.url,
          originX: "center",
          originY: "center",
          left: PREVIEW_SIZE / 2 + offset,
          top: PREVIEW_SIZE / 2 - 50 + offset,
          selectable: true,
          printable: true,
          cornerColor: "#4f46e5",
          cornerStrokeColor: "#ffffff",
          cornerSize: 9,
          transparentCorners: false
        });
        img.scaleToWidth(150);
        state.canvas.add(img);
        bringDecorationsToFront();
        state.canvas.setActiveObject(img);
        state.canvas.renderAll();
        renderClipartList();
        syncStateToForm();
      },
      { crossOrigin: "anonymous" }
    );
  }
  function replaceClipartLayer(targetObj, clipartItem) {
    if (!fabric) return;
    fabric.Image.fromURL(
      clipartItem.url,
      function(newImg) {
        if (!newImg) return;
        targetObj.setElement(newImg.getElement());
        targetObj.clipartName = clipartItem.name;
        targetObj.clipartUrl = clipartItem.url;
        if (state.canvas) state.canvas.renderAll();
        renderClipartList();
        syncStateToForm();
      },
      { crossOrigin: "anonymous" }
    );
  }
  function renderClipartList() {
    if (!elements.clipartList) return;
    elements.clipartList.innerHTML = "";
    const clipartLayers = getLayersByType("clipart");
    if (clipartLayers.length === 0) {
      elements.clipartList.innerHTML = `
      <div class="pod-empty-state">
        <span>${t("emptyClipartList", 'No clipart graphics on canvas. Click "Add Clipart" to select one.')}</span>
      </div>
    `;
      return;
    }
    const activeObj = state.canvas ? state.canvas.getActiveObject() : null;
    clipartLayers.forEach((obj) => {
      const item = document.createElement("div");
      item.className = "pod-layer-item" + (activeObj === obj ? " active" : "");
      item.dataset.podId = obj.podId;
      item.innerHTML = `
      <div class="pod-layer-info">
        <img src="${obj.clipartUrl}" class="pod-layer-thumb" alt="Clipart" />
        <div class="pod-layer-texts">
          <span class="pod-layer-title">${escapeHtml(obj.clipartName || "Clipart")}</span>
          <span class="pod-layer-subtitle">${Math.round(obj.getScaledWidth())} \xD7 ${Math.round(obj.getScaledHeight())} px</span>
        </div>
      </div>
      <div class="pod-layer-actions">
        <button type="button" class="pod-btn-edit" title="${escapeHtml(t("titleChangeClipart", "Change clipart"))}">\u270F\uFE0F ${escapeHtml(t("btnChange", "Change"))}</button>
        <button type="button" class="pod-btn-delete" title="${escapeHtml(t("titleDeleteClipart", "Delete clipart"))}">\u{1F5D1}\uFE0F ${escapeHtml(t("btnDelete", "Delete"))}</button>
      </div>
    `;
      item.addEventListener("click", (e) => {
        if (e.target.closest("button")) return;
        if (state.canvas) {
          state.canvas.setActiveObject(obj);
          state.canvas.renderAll();
        }
        highlightActiveLayerItem(obj);
        openClipartModal(obj);
      });
      item.querySelector(".pod-btn-edit").addEventListener("click", (e) => {
        e.stopPropagation();
        openClipartModal(obj);
      });
      item.querySelector(".pod-btn-delete").addEventListener("click", (e) => {
        e.stopPropagation();
        if (state.canvas) {
          state.canvas.remove(obj);
          state.canvas.renderAll();
        }
        renderClipartList();
        syncStateToForm();
      });
      elements.clipartList.appendChild(item);
    });
  }
  function initClipartEvents() {
    if (elements.btnAddClipart) {
      elements.btnAddClipart.addEventListener("click", () => openClipartModal(null));
    }
    if (elements.clipartModalBtnClose) {
      elements.clipartModalBtnClose.addEventListener("click", closeClipartModal);
    }
    if (elements.clipartModal) {
      elements.clipartModal.addEventListener("click", (e) => {
        if (e.target === elements.clipartModal) closeClipartModal();
      });
    }
  }

  // assets/js/src/photo.js
  function openPhotoModal(targetObj = null) {
    state.editingPhotoObj = targetObj;
    if (elements.photoModalTitle) {
      elements.photoModalTitle.textContent = targetObj ? t("modalChangePhoto", "Change Uploaded Photo") : t("modalUploadPhoto", "Upload Personal Photo");
    }
    if (elements.photoModal) {
      elements.photoModal.style.display = "flex";
    }
  }
  function closePhotoModal() {
    state.editingPhotoObj = null;
    if (elements.photoModal) {
      elements.photoModal.style.display = "none";
    }
  }
  function handlePhotoUploadFiles(files) {
    if (!files || files.length === 0) return;
    if (state.editingPhotoObj) {
      replacePhotoLayer(state.editingPhotoObj, files[0]);
    } else {
      files.forEach((f) => addPhotoLayer(f));
    }
    closePhotoModal();
  }
  function addPhotoLayer(file) {
    if (!file.type.match(/^image\/(png|jpeg|webp)$/)) {
      alert(t("invalidPhoto", "Please select a valid image file (PNG, JPG, WebP)"));
      return;
    }
    const reader = new FileReader();
    reader.onload = function(f) {
      const dataUrl = f.target.result;
      fabric.Image.fromURL(dataUrl, function(img) {
        if (!img || !state.canvas) return;
        const count = getLayersByType("photo").length;
        const offset = count % 3 * 25;
        img.set({
          podType: "photo",
          podId: "photo_" + Date.now() + "_" + Math.floor(Math.random() * 1e3),
          photoName: file.name,
          originX: "center",
          originY: "center",
          left: PREVIEW_SIZE / 2 + offset,
          top: PREVIEW_SIZE / 2 - 30 + offset,
          selectable: true,
          printable: true,
          cornerColor: "#06b6d4",
          cornerStrokeColor: "#ffffff",
          cornerSize: 9,
          transparentCorners: false
        });
        img.scaleToWidth(160);
        state.canvas.add(img);
        bringDecorationsToFront();
        state.canvas.setActiveObject(img);
        state.canvas.renderAll();
        renderPhotoList();
        syncStateToForm();
      });
    };
    reader.readAsDataURL(file);
  }
  function replacePhotoLayer(targetObj, file) {
    if (!file.type.match(/^image\/(png|jpeg|jpg|webp)$/)) {
      alert(t("invalidPhoto", "Please select a valid image file (PNG, JPG, WebP)"));
      return;
    }
    const reader = new FileReader();
    reader.onload = function(f) {
      const dataUrl = f.target.result;
      fabric.Image.fromURL(dataUrl, function(newImg) {
        if (!newImg) return;
        targetObj.setElement(newImg.getElement());
        targetObj.photoName = file.name;
        if (state.canvas) state.canvas.renderAll();
        renderPhotoList();
        syncStateToForm();
      });
    };
    reader.readAsDataURL(file);
  }
  function renderPhotoList() {
    if (!elements.photoList) return;
    elements.photoList.innerHTML = "";
    const photoLayers = getLayersByType("photo");
    if (photoLayers.length === 0) {
      elements.photoList.innerHTML = `
      <div class="pod-empty-state">
        <span>${t("emptyPhotoList", 'No uploaded photos on canvas. Click "Add Photo" to upload.')}</span>
      </div>
    `;
      return;
    }
    const activeObj = state.canvas ? state.canvas.getActiveObject() : null;
    photoLayers.forEach((obj) => {
      const item = document.createElement("div");
      item.className = "pod-layer-item" + (activeObj === obj ? " active" : "");
      item.dataset.podId = obj.podId;
      const thumbSrc = obj.toDataURL ? obj.toDataURL({ format: "jpeg", quality: 0.3, multiplier: 0.2 }) : "";
      item.innerHTML = `
      <div class="pod-layer-info">
        <img src="${thumbSrc}" class="pod-layer-thumb" alt="Photo" />
        <div class="pod-layer-texts">
          <span class="pod-layer-title">${escapeHtml(obj.photoName || t("uploadedPhoto", "Uploaded Photo"))}</span>
          <span class="pod-layer-subtitle">${Math.round(obj.getScaledWidth())} \xD7 ${Math.round(obj.getScaledHeight())} px</span>
        </div>
      </div>
      <div class="pod-layer-actions">
        <button type="button" class="pod-btn-edit" title="${escapeHtml(t("titleChangePhoto", "Change photo"))}">\u270F\uFE0F ${escapeHtml(t("btnChange", "Change"))}</button>
        <button type="button" class="pod-btn-delete" title="${escapeHtml(t("titleDeletePhoto", "Delete photo"))}">\u{1F5D1}\uFE0F ${escapeHtml(t("btnDelete", "Delete"))}</button>
      </div>
    `;
      item.addEventListener("click", (e) => {
        if (e.target.closest("button")) return;
        if (state.canvas) {
          state.canvas.setActiveObject(obj);
          state.canvas.renderAll();
        }
        highlightActiveLayerItem(obj);
        openPhotoModal(obj);
      });
      item.querySelector(".pod-btn-edit").addEventListener("click", (e) => {
        e.stopPropagation();
        openPhotoModal(obj);
      });
      item.querySelector(".pod-btn-delete").addEventListener("click", (e) => {
        e.stopPropagation();
        if (state.canvas) {
          state.canvas.remove(obj);
          state.canvas.renderAll();
        }
        renderPhotoList();
        syncStateToForm();
      });
      elements.photoList.appendChild(item);
    });
  }
  function initPhotoEvents() {
    if (elements.btnAddPhoto) {
      elements.btnAddPhoto.addEventListener("click", () => openPhotoModal(null));
    }
    if (elements.photoModalBtnClose) {
      elements.photoModalBtnClose.addEventListener("click", closePhotoModal);
    }
    if (elements.photoModal) {
      elements.photoModal.addEventListener("click", (e) => {
        if (e.target === elements.photoModal) closePhotoModal();
      });
    }
    if (elements.dropzone && elements.fileInput) {
      elements.dropzone.addEventListener("click", () => elements.fileInput.click());
      elements.fileInput.addEventListener("change", (e) => {
        const files = Array.from(e.target.files || []);
        handlePhotoUploadFiles(files);
        elements.fileInput.value = "";
      });
      elements.dropzone.addEventListener("dragover", (e) => {
        e.preventDefault();
        elements.dropzone.style.borderColor = "#4f46e5";
      });
      elements.dropzone.addEventListener("dragleave", () => {
        elements.dropzone.style.borderColor = "#cbd5e1";
      });
      elements.dropzone.addEventListener("drop", (e) => {
        e.preventDefault();
        elements.dropzone.style.borderColor = "#cbd5e1";
        const files = Array.from(e.dataTransfer.files || []);
        handlePhotoUploadFiles(files);
      });
    }
  }

  // assets/js/src/mockup.js
  function renderMockupGrid() {
    if (!elements.mockupsGrid || !config.mockups) return;
    elements.mockupsGrid.innerHTML = "";
    config.mockups.forEach((m, idx) => {
      const card = document.createElement("div");
      card.className = "pod-mockup-card" + (idx === 0 ? " active" : "");
      card.dataset.id = m.id;
      card.innerHTML = `
      <img src="${m.url}" alt="${m.name}" class="pod-mockup-thumb" />
      <span class="pod-mockup-title">${m.name}</span>
    `;
      card.addEventListener("click", () => selectMockup(m, card));
      elements.mockupsGrid.appendChild(card);
    });
  }
  function selectMockup(mockup, activeCard) {
    state.currentMockup = mockup;
    document.querySelectorAll(".pod-mockup-card").forEach((c) => c.classList.remove("active"));
    if (activeCard) activeCard.classList.add("active");
    loadMockupImage(mockup.url);
  }
  function loadMockupImage(url) {
    if (!fabric || !state.canvas) return;
    fabric.Image.fromURL(
      url,
      function(img) {
        if (!img) return;
        if (state.mockupImg) {
          state.canvas.remove(state.mockupImg);
        }
        state.mockupImg = img;
        state.mockupImg.set({
          podType: "mockup",
          podId: "mockup_base",
          originX: "center",
          originY: "center",
          left: PREVIEW_SIZE / 2,
          top: PREVIEW_SIZE / 2,
          selectable: false,
          evented: false,
          printable: false
        });
        state.mockupImg.scaleToWidth(PREVIEW_SIZE * 0.94);
        state.canvas.add(state.mockupImg);
        state.mockupImg.sendToBack();
        state.canvas.renderAll();
        syncStateToForm();
      },
      { crossOrigin: "anonymous" }
    );
  }

  // assets/js/src/canvas.js
  function renderAllLayerLists() {
    renderTextList();
    renderClipartList();
    renderPhotoList();
  }
  function initCanvas() {
    if (!fabric) return;
    state.canvas = new fabric.Canvas("pod-live-canvas", {
      width: PREVIEW_SIZE,
      height: PREVIEW_SIZE,
      backgroundColor: "#f8fafc",
      selection: true,
      preserveObjectStacking: true
    });
    state.canvas.on("object:modified", function() {
      syncStateToForm();
      renderAllLayerLists();
    });
    state.canvas.on("selection:created", onObjectSelected);
    state.canvas.on("selection:updated", onObjectSelected);
    state.canvas.on("selection:cleared", onSelectionCleared);
    state.canvas.on("mouse:dblclick", function(opt) {
      const target = opt.target;
      if (!target) return;
      if (target.podType === "text") {
        openTextModal(target);
      } else if (target.podType === "clipart") {
        openClipartModal(target);
      } else if (target.podType === "photo") {
        openPhotoModal(target);
      }
    });
  }
  function onObjectSelected(e) {
    const selected = e.selected?.[0];
    if (!selected) return;
    highlightActiveLayerItem(selected);
  }
  function onSelectionCleared() {
    document.querySelectorAll(".pod-layer-item").forEach((item) => item.classList.remove("active"));
  }
  function resetStudio() {
    if (!state.canvas) return;
    const objects = state.canvas.getObjects().slice();
    objects.forEach((obj) => {
      if (obj.podType && obj.podType !== "mockup") {
        state.canvas.remove(obj);
      }
    });
    if (state.currentMockup) {
      loadMockupImage(state.currentMockup.url);
    }
    renderAllLayerLists();
    syncStateToForm();
  }
  function getTemplateScale() {
    const designWidth = config.template?.print_spec?.width_px || 2400;
    return PREVIEW_SIZE / designWidth;
  }
  function renderAllTemplateFields() {
    if (!config.template) return;
    const scale = getTemplateScale();
    renderFixedTemplateLayers(scale);
    if (Array.isArray(config.template.fields)) {
      config.template.fields.forEach((field) => {
        const val = state.templateValues ? state.templateValues[field.id] : void 0;
        updateTemplateField(field.id, val);
      });
    }
  }
  function updateTemplateField(fieldId, value) {
    if (!state.canvas || !config.template) return;
    const field = (config.template.fields || []).find((f) => f.id === fieldId);
    if (!field) return;
    const scale = getTemplateScale();
    if (field.type === "text") {
      renderTextSlot(field, value, scale);
    } else if (field.type === "preset_picker") {
      renderPresetImageSlot(field, value, scale);
    } else if (field.type === "repeater_counter") {
      renderRepeaterSlot(field, value, scale);
    } else if (field.type === "layer_selector") {
      renderLayerSelectorSlot(field, value, scale);
    }
  }
  function renderTextSlot(field, value, scale) {
    const targetLayer = (config.template.layers || []).find((l) => l.id === field.target_layer || l.id === field.id);
    const text = value !== void 0 ? String(value) : field.default_value || targetLayer?.default_value || "";
    const rawX = field.position?.x !== void 0 ? field.position.x : targetLayer ? targetLayer.x + (targetLayer.width || 0) / 2 : 1200;
    const rawY = field.position?.y !== void 0 ? field.position.y : targetLayer ? targetLayer.y + (targetLayer.height || 0) / 2 : 1200;
    const x = rawX * scale;
    const y = rawY * scale;
    const style = field.style || {};
    const initialFontSize = (style.font_size_px || targetLayer?.font_size_pt || 48) * scale;
    const minFontSize = (style.min_font_size_px || targetLayer?.behavior?.min_font_size_pt || 18) * scale;
    const maxWidth = (style.max_width_px || targetLayer?.width || 800) * scale;
    const fontFamily = style.font_family || targetLayer?.font_family || "Montserrat";
    const color = style.color || targetLayer?.color || "#1e293b";
    const rotation = style.rotation !== void 0 ? style.rotation : targetLayer?.rotation || 0;
    const align = style.align || targetLayer?.text_align || "center";
    const fontWeight = style.font_weight || "normal";
    if (state.templateObjects[field.id]) {
      state.canvas.remove(state.templateObjects[field.id]);
      delete state.templateObjects[field.id];
    }
    if (!text.trim()) {
      if (state.templateMetrics) {
        state.templateMetrics[field.id] = { effectiveFontSize: initialFontSize, isShrunk: false };
      }
      state.canvas.renderAll();
      syncStateToForm();
      return;
    }
    const textObj = new fabric.Text(text, {
      left: x,
      top: y,
      fontFamily,
      fontSize: initialFontSize,
      fontWeight,
      fill: color,
      textAlign: align,
      originX: align === "center" ? "center" : align === "right" ? "right" : "left",
      originY: "middle",
      angle: rotation,
      selectable: false,
      evented: false,
      podType: "text",
      podFieldId: field.id,
      podZIndex: targetLayer?.z_index || 20,
      textBaseline: "alphabetic"
    });
    const measuredWidth = textObj.width;
    let effectiveFontSize = initialFontSize;
    let isShrunk = false;
    if (measuredWidth > maxWidth && measuredWidth > 0) {
      const shrinkRatio = maxWidth / measuredWidth;
      effectiveFontSize = Math.max(minFontSize, Math.floor(initialFontSize * shrinkRatio));
      textObj.set("fontSize", effectiveFontSize);
      textObj.initDimensions();
      textObj.setCoords();
      isShrunk = true;
    }
    if (state.templateMetrics) {
      state.templateMetrics[field.id] = {
        effectiveFontSize: Math.round(effectiveFontSize / scale),
        isShrunk
      };
    }
    state.templateObjects[field.id] = textObj;
    state.canvas.add(textObj);
    state.canvas.bringToFront(textObj);
    state.canvas.renderAll();
    syncStateToForm();
  }
  var templateImageCache = {};
  function renderPresetImageSlot(field, value, scale) {
    const targetLayer = (config.template.layers || []).find((l) => l.id === field.target_layer || l.id === field.id);
    const optionId = value || field.default_value || field.options?.[0]?.id;
    const option = (field.options || []).find((o) => o.id === optionId) || field.options?.[0];
    const imgUrl = option?.url || option?.thumbnail_url || targetLayer?.placeholder_url;
    if (!imgUrl) return;
    const rawX = field.position?.x !== void 0 ? field.position.x : targetLayer ? targetLayer.x + (targetLayer.width || 0) / 2 : 1200;
    const rawY = field.position?.y !== void 0 ? field.position.y : targetLayer ? targetLayer.y + (targetLayer.height || 0) / 2 : 1200;
    const rawW = field.position?.width_px !== void 0 ? field.position.width_px : targetLayer?.width || 200;
    const rawH = field.position?.height_px !== void 0 ? field.position.height_px : targetLayer?.height || 200;
    const x = rawX * scale;
    const y = rawY * scale;
    const targetW = rawW * scale;
    const targetH = rawH * scale;
    function applyPresetImage(imgElement) {
      if (state.templateObjects[field.id]) {
        state.canvas.remove(state.templateObjects[field.id]);
        delete state.templateObjects[field.id];
      }
      const svgObj = new fabric.Image(imgElement, {
        left: x,
        top: y,
        originX: "center",
        originY: "middle",
        selectable: false,
        evented: false,
        podType: "clipart",
        clipartName: option?.label || option?.id || "Icon",
        clipartUrl: imgUrl,
        podFieldId: field.id,
        podZIndex: targetLayer?.z_index || 15
      });
      svgObj.scaleToWidth(targetW);
      if (svgObj.getScaledHeight() > targetH) {
        svgObj.scaleToHeight(targetH);
      }
      state.templateObjects[field.id] = svgObj;
      state.canvas.add(svgObj);
      state.canvas.bringToFront(svgObj);
      state.canvas.renderAll();
      syncStateToForm();
    }
    if (templateImageCache[imgUrl]) {
      applyPresetImage(templateImageCache[imgUrl]);
    } else {
      fabric.Image.fromURL(
        imgUrl,
        (img) => {
          if (!img) return;
          const elem = img.getElement();
          templateImageCache[imgUrl] = elem;
          applyPresetImage(elem);
        },
        { crossOrigin: "anonymous" }
      );
    }
  }
  function renderLayerSelectorSlot(field, value, scale) {
    const selectedId = value || field.default_value || field.options?.[0]?.layer_id || field.options?.[0]?.id;
    const opt = (field.options || []).find((o) => o.id === selectedId || o.layer_id === selectedId);
    const targetLayerId = opt ? opt.layer_id || opt.id : selectedId;
    const groupLayers = (config.template.layers || []).filter((l) => l.group_id === field.id);
    groupLayers.forEach((layer) => {
      const isChosen = layer.id === targetLayerId || layer.id === selectedId;
      let fabricObj = state.templateObjects[layer.id];
      if (!fabricObj && layer.url) {
        fabric.Image.fromURL(
          layer.url,
          (img) => {
            if (!img) return;
            img.set({
              left: (layer.x || 0) * scale,
              top: (layer.y || 0) * scale,
              scaleX: (layer.width || img.width) * scale / img.width,
              scaleY: (layer.height || img.height) * scale / img.height,
              angle: layer.rotation || 0,
              selectable: false,
              evented: false,
              visible: isChosen,
              podType: "template_layer",
              podLayerId: layer.id,
              podLayerName: layer.name,
              podLayerUrl: layer.url,
              podZIndex: layer.z_index || 5
            });
            state.templateObjects[layer.id] = img;
            state.canvas.add(img);
            sortTemplateObjectsZIndex();
            state.canvas.renderAll();
          },
          { crossOrigin: "anonymous" }
        );
      } else if (fabricObj) {
        fabricObj.set("visible", isChosen);
        state.canvas.renderAll();
      }
    });
    syncStateToForm();
  }
  function renderFixedTemplateLayers(scale) {
    if (!config.template || !Array.isArray(config.template.layers)) return;
    config.template.layers.forEach((layer) => {
      if (layer.type === "fixed_image" && layer.url) {
        if (state.templateObjects[layer.id]) return;
        fabric.Image.fromURL(
          layer.url,
          (img) => {
            if (!img) return;
            img.set({
              left: (layer.x || 0) * scale,
              top: (layer.y || 0) * scale,
              scaleX: (layer.width || img.width) * scale / img.width,
              scaleY: (layer.height || img.height) * scale / img.height,
              angle: layer.rotation || 0,
              selectable: false,
              evented: false,
              visible: true,
              podType: "template_fixed",
              podLayerId: layer.id,
              podLayerName: layer.name,
              podLayerUrl: layer.url,
              podZIndex: layer.z_index || 1
            });
            state.templateObjects[layer.id] = img;
            state.canvas.add(img);
            sortTemplateObjectsZIndex();
            state.canvas.renderAll();
          },
          { crossOrigin: "anonymous" }
        );
      }
    });
  }
  function sortTemplateObjectsZIndex() {
    if (!state.canvas) return;
    const objects = state.canvas.getObjects().slice();
    objects.sort((a, b) => (a.podZIndex || 10) - (b.podZIndex || 10));
    objects.forEach((obj, idx) => {
      state.canvas.moveTo(obj, idx);
    });
  }
  function renderRepeaterSlot(field, value, scale) {
    const min = field.min !== void 0 ? field.min : 1;
    const max = field.max !== void 0 ? field.max : 50;
    let count = parseInt(value !== void 0 ? value : field.default_value || min, 10);
    if (isNaN(count)) count = min;
    count = Math.max(min, Math.min(max, count));
    const container = field.container_bounds || {};
    const isGrid = container.distribution === "grid" || container.distribution === "rectangle" || !!container.cols;
    const centerX = (container.x || 1200) * scale;
    const centerY = (container.y || 1100) * scale;
    const maxContainerW = (container.max_width_px || container.width_px || 700) * scale;
    const maxContainerH = (container.max_height_px || container.height_px || 400) * scale;
    const subImg = field.sub_image || {};
    let itemW = (subImg.width_px || 32) * scale;
    let itemH = (subImg.height_px || 64) * scale;
    let gapX = (container.gap_x_px || container.gap_px || 12) * scale;
    let gapY = (container.gap_y_px || container.gap_px || 12) * scale;
    const url = subImg.url;
    if (!url) return;
    function buildRepeaterItems(imgElement) {
      if (state.templateObjects[field.id]) {
        if (Array.isArray(state.templateObjects[field.id])) {
          state.templateObjects[field.id].forEach((obj) => state.canvas.remove(obj));
        } else {
          state.canvas.remove(state.templateObjects[field.id]);
        }
        state.templateObjects[field.id] = [];
      }
      const createdObjs = [];
      if (isGrid) {
        const maxCols = container.cols || container.max_per_row || 6;
        const numRows = Math.ceil(count / maxCols);
        const effectiveCols = Math.ceil(count / numRows);
        let totalGridH = numRows * itemH + (numRows - 1) * gapY;
        let maxRowItems = Math.min(effectiveCols, count);
        let totalGridW = maxRowItems * itemW + (maxRowItems - 1) * gapX;
        let scaleFactor = 1;
        if (totalGridW > maxContainerW) {
          scaleFactor = Math.min(scaleFactor, maxContainerW / totalGridW);
        }
        if (totalGridH > maxContainerH) {
          scaleFactor = Math.min(scaleFactor, maxContainerH / totalGridH);
        }
        if (scaleFactor < 1) {
          itemW *= scaleFactor;
          itemH *= scaleFactor;
          gapX *= scaleFactor;
          gapY *= scaleFactor;
          totalGridH = numRows * itemH + (numRows - 1) * gapY;
        }
        const gridStartY = centerY - totalGridH / 2 + itemH / 2;
        let itemIdx = 0;
        for (let r = 0; r < numRows; r++) {
          const itemsInThisRow = Math.min(effectiveCols, count - itemIdx);
          const rowW = itemsInThisRow * itemW + (itemsInThisRow - 1) * gapX;
          const rowStartX = centerX - rowW / 2 + itemW / 2;
          const rowY = gridStartY + r * (itemH + gapY);
          for (let c = 0; c < itemsInThisRow; c++) {
            const posX = rowStartX + c * (itemW + gapX);
            const itemObj = new fabric.Image(imgElement, {
              left: posX,
              top: rowY,
              originX: "center",
              originY: "middle",
              selectable: false,
              evented: false,
              podType: "clipart",
              clipartName: `Repeater Item ${itemIdx + 1}`,
              clipartUrl: url,
              podFieldId: field.id
            });
            itemObj.scaleToWidth(itemW);
            if (itemObj.getScaledHeight() > itemH) {
              itemObj.scaleToHeight(itemH);
            }
            state.canvas.add(itemObj);
            state.canvas.bringToFront(itemObj);
            createdObjs.push(itemObj);
            itemIdx++;
          }
        }
      } else {
        let gap = gapX;
        let totalW = count * itemW + (count - 1) * gap;
        if (totalW > maxContainerW && count > 1) {
          gap = (maxContainerW - count * itemW) / (count - 1);
          if (gap < 2) {
            const shrinkFactor = maxContainerW / (count * itemW + (count - 1) * 2);
            itemW *= shrinkFactor;
            itemH *= shrinkFactor;
            gap = 2;
            totalW = count * itemW + (count - 1) * gap;
          } else {
            totalW = count * itemW + (count - 1) * gap;
          }
        }
        const startX = centerX - totalW / 2 + itemW / 2;
        for (let i = 0; i < count; i++) {
          const posX = startX + i * (itemW + gap);
          const itemObj = new fabric.Image(imgElement, {
            left: posX,
            top: centerY,
            originX: "center",
            originY: "middle",
            selectable: false,
            evented: false,
            podType: "clipart",
            clipartName: `Repeater Item ${i + 1}`,
            clipartUrl: url,
            podFieldId: field.id
          });
          itemObj.scaleToWidth(itemW);
          if (itemObj.getScaledHeight() > itemH) {
            itemObj.scaleToHeight(itemH);
          }
          state.canvas.add(itemObj);
          state.canvas.bringToFront(itemObj);
          createdObjs.push(itemObj);
        }
      }
      state.templateObjects[field.id] = createdObjs;
      state.canvas.renderAll();
      syncStateToForm();
    }
    if (templateImageCache[url]) {
      buildRepeaterItems(templateImageCache[url]);
    } else {
      fabric.Image.fromURL(
        url,
        (img) => {
          if (!img) return;
          const elem = img.getElement();
          templateImageCache[url] = elem;
          buildRepeaterItems(elem);
        },
        { crossOrigin: "anonymous" }
      );
    }
  }

  // assets/js/src/cart.js
  function onAddToCartSubmit(e) {
    if (!elements.stateInput || !state.canvas) return;
    syncStateToForm();
    const printableLayers = state.canvas.getObjects().filter((obj) => obj.podType && obj.podType !== "mockup");
    if (printableLayers.length === 0) {
      e.preventDefault();
      alert(t("emptyDesignAlert", "Please add at least one customization (text, clipart, or photo) to your design."));
      return false;
    }
    const textLayers = getLayersByType("text");
    if (textLayers.length > 0) {
      const hasValidText = textLayers.some((t2) => t2.text && t2.text.trim().length > 0);
      if (!hasValidText) {
        e.preventDefault();
        alert(t("emptyTextAlert", "Please enter custom text for your design."));
        return false;
      }
    }
    if (!elements.stateInput.value) {
      e.preventDefault();
      alert(t("saveStateFailed", "Could not save custom design state. Please try again."));
      return false;
    }
  }

  // assets/js/src/form.js
  function initTemplateForm(template2, values, onFieldChange) {
    const container = document.getElementById("pod-template-form-fields");
    if (!container || !template2 || !Array.isArray(template2.fields)) return;
    container.innerHTML = "";
    template2.fields.forEach((field) => {
      const fieldGroup = document.createElement("div");
      fieldGroup.className = "pod-form-group";
      fieldGroup.setAttribute("data-field-id", field.id);
      const label = document.createElement("label");
      label.className = "pod-form-label";
      label.textContent = field.label || field.id;
      fieldGroup.appendChild(label);
      if (field.type === "text") {
        renderTextInput(fieldGroup, field, values[field.id], onFieldChange);
      } else if (field.type === "preset_picker") {
        renderPresetPicker(fieldGroup, field, values[field.id], onFieldChange);
      } else if (field.type === "repeater_counter") {
        renderRepeaterCounter(fieldGroup, field, values[field.id], onFieldChange);
      } else if (field.type === "layer_selector") {
        renderLayerSelector(fieldGroup, field, values[field.id], onFieldChange);
      }
      container.appendChild(fieldGroup);
    });
  }
  function renderTextInput(parent, field, currentValue, onFieldChange) {
    const wrapper = document.createElement("div");
    wrapper.className = "pod-input-wrapper";
    const input = document.createElement("input");
    input.type = "text";
    input.className = "pod-form-input";
    input.id = `pod-field-${field.id}`;
    input.placeholder = field.placeholder || "";
    input.value = currentValue !== void 0 ? currentValue : field.default_value || "";
    const debouncedChange = debounce((val) => {
      onFieldChange(field.id, val);
    }, 60);
    input.addEventListener("input", (e) => {
      debouncedChange(e.target.value);
    });
    wrapper.appendChild(input);
    parent.appendChild(wrapper);
  }
  function renderPresetPicker(parent, field, currentValue, onFieldChange) {
    const grid = document.createElement("div");
    grid.className = "pod-preset-grid";
    const activeId = currentValue || field.default_value || field.options?.[0]?.id;
    (field.options || []).forEach((opt) => {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = `pod-preset-btn ${opt.id === activeId ? "active" : ""}`;
      btn.setAttribute("data-option-id", opt.id);
      btn.title = opt.label || opt.id;
      btn.innerHTML = `
      <div class="pod-preset-thumb">
        <img src="${opt.url}" alt="${opt.label || opt.id}" />
      </div>
      <span class="pod-preset-label">${opt.label || opt.id}</span>
    `;
      btn.addEventListener("click", (e) => {
        e.preventDefault();
        grid.querySelectorAll(".pod-preset-btn").forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        onFieldChange(field.id, opt.id);
      });
      grid.appendChild(btn);
    });
    parent.appendChild(grid);
  }
  function renderRepeaterCounter(parent, field, currentValue, onFieldChange) {
    const min = field.min !== void 0 ? field.min : 1;
    const max = field.max !== void 0 ? field.max : 20;
    let val = parseInt(currentValue !== void 0 ? currentValue : field.default_value || min, 10);
    if (isNaN(val)) val = min;
    const stepper = document.createElement("div");
    stepper.className = "pod-stepper-control";
    const btnMinus = document.createElement("button");
    btnMinus.type = "button";
    btnMinus.className = "pod-stepper-btn minus";
    btnMinus.textContent = "\u2212";
    const input = document.createElement("input");
    input.type = "number";
    input.className = "pod-stepper-input";
    input.value = val;
    input.min = min;
    input.max = max;
    input.readOnly = true;
    const btnPlus = document.createElement("button");
    btnPlus.type = "button";
    btnPlus.className = "pod-stepper-btn plus";
    btnPlus.textContent = "+";
    const helperText = document.createElement("span");
    helperText.className = "pod-stepper-hint";
    helperText.textContent = `${field.sub_image ? "Items" : "Count"}: min ${min}, max ${max}`;
    function updateValue(newVal) {
      if (newVal < min) newVal = min;
      if (newVal > max) newVal = max;
      val = newVal;
      input.value = val;
      btnMinus.disabled = val <= min;
      btnPlus.disabled = val >= max;
      onFieldChange(field.id, val);
    }
    btnMinus.addEventListener("click", (e) => {
      e.preventDefault();
      updateValue(val - 1);
    });
    btnPlus.addEventListener("click", (e) => {
      e.preventDefault();
      updateValue(val + 1);
    });
    stepper.appendChild(btnMinus);
    stepper.appendChild(input);
    stepper.appendChild(btnPlus);
    stepper.appendChild(helperText);
    btnMinus.disabled = val <= min;
    btnPlus.disabled = val >= max;
    parent.appendChild(stepper);
  }
  function renderLayerSelector(parent, field, currentValue, onFieldChange) {
    const grid = document.createElement("div");
    grid.className = "pod-preset-grid pod-layer-selector-grid";
    const activeId = currentValue || field.default_value || field.options?.[0]?.layer_id || field.options?.[0]?.id;
    (field.options || []).forEach((opt) => {
      const optValue = opt.layer_id || opt.id;
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = `pod-preset-btn ${optValue === activeId || opt.id === activeId ? "active" : ""}`;
      btn.setAttribute("data-option-id", optValue);
      btn.title = opt.label || opt.id;
      const thumbUrl = opt.thumbnail_url || opt.url || "";
      btn.innerHTML = `
      <div class="pod-preset-thumb">
        ${thumbUrl ? `<img src="${thumbUrl}" alt="${opt.label || opt.id}" />` : `<span style="font-size: 11px; font-weight: 600;">${opt.label || opt.id}</span>`}
      </div>
      <span class="pod-preset-label">${opt.label || opt.id}</span>
    `;
      btn.addEventListener("click", (e) => {
        e.preventDefault();
        grid.querySelectorAll(".pod-preset-btn").forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        onFieldChange(field.id, optValue);
      });
      grid.appendChild(btn);
    });
    parent.appendChild(grid);
  }

  // assets/js/src/preview.js
  function openPreviewModal() {
    console.log("[POD Customizer] \u{1F50D} openPreviewModal() invoked.");
    const modal = document.getElementById("pod-preview-modal");
    const img = document.getElementById("pod-preview-modal-img");
    const specs = document.getElementById("pod-preview-specs");
    console.log("[POD Customizer] Element status check:", {
      hasCanvas: !!state.canvas,
      hasModal: !!modal,
      hasImg: !!img,
      isTemplateMode
    });
    if (!state.canvas || !modal) {
      console.error("[POD Customizer] \u274C Cannot open modal: canvas or modal element missing from DOM!");
      return;
    }
    try {
      const dataUrl = state.canvas.toDataURL({
        format: "png",
        multiplier: 2,
        quality: 1
      });
      console.log("[POD Customizer] \u2705 Canvas snapshot generated. Data length:", dataUrl.length);
      if (img) {
        img.src = dataUrl;
      }
    } catch (err) {
      console.error("[POD Customizer] \u274C Failed to export canvas snapshot:", err);
    }
    if (specs) {
      specs.innerHTML = "";
      const tpl = config.template;
      if (isTemplateMode && tpl && Array.isArray(tpl.fields)) {
        tpl.fields.forEach((field) => {
          const val = state.templateValues ? state.templateValues[field.id] : field.default_value;
          if (val !== void 0 && String(val).trim() !== "") {
            const pill = document.createElement("span");
            pill.className = "pod-preview-spec-pill";
            let displayVal = String(val);
            if (field.type === "repeater_counter") {
              displayVal = `${val} items`;
            } else if (field.type === "preset_picker") {
              const opt = (field.options || []).find((o) => o.id === val);
              displayVal = opt ? opt.label || opt.id : val;
            }
            pill.innerHTML = `<strong>${field.label || field.id}:</strong> ${displayVal}`;
            specs.appendChild(pill);
          }
        });
        specs.style.display = specs.children.length ? "flex" : "none";
      } else {
        specs.style.display = "none";
      }
    }
    if (modal.parentNode !== document.body) {
      document.body.appendChild(modal);
    }
    modal.style.setProperty("display", "flex", "important");
    modal.style.setProperty("z-index", "9999999", "important");
    modal.classList.add("is-open");
    document.body.style.overflow = "hidden";
    console.log("[POD Customizer] \u{1F680} Preview modal displayed successfully!");
  }
  function closePreviewModal() {
    console.log("[POD Customizer] \u{1F512} closePreviewModal() invoked.");
    const modal = document.getElementById("pod-preview-modal");
    if (!modal) return;
    modal.style.setProperty("display", "none", "important");
    modal.classList.remove("is-open");
    document.body.style.overflow = "";
  }
  if (typeof window !== "undefined") {
    window.podOpenPreview = openPreviewModal;
    window.podClosePreview = closePreviewModal;
  }
  function initPreviewModal() {
    console.log("[POD Customizer] \u{1F6E0}\uFE0F initPreviewModal() setting up listeners.");
    const checkElements = () => {
      const btn = document.getElementById("pod-btn-preview");
      const quickBtn = document.getElementById("pod-btn-quick-preview");
      const modal = document.getElementById("pod-preview-modal");
      console.log("[POD Customizer] Preview elements found in DOM:", {
        btnPreview: btn,
        btnQuickPreview: quickBtn,
        previewModal: modal
      });
    };
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", checkElements);
    } else {
      checkElements();
    }
    document.addEventListener("click", (e) => {
      const previewTrigger = e.target.closest("#pod-btn-preview, #pod-btn-quick-preview");
      if (previewTrigger) {
        console.log("[POD Customizer] \u{1F5B1}\uFE0F Click detected on Preview Trigger:", previewTrigger.id, e.target);
        e.preventDefault();
        e.stopPropagation();
        openPreviewModal();
        return;
      }
      const closeTrigger = e.target.closest("#pod-preview-modal-btn-close, #pod-preview-modal-btn-done");
      if (closeTrigger) {
        console.log("[POD Customizer] \u{1F5B1}\uFE0F Click detected on Close Button:", closeTrigger.id);
        e.preventDefault();
        e.stopPropagation();
        closePreviewModal();
        return;
      }
      const modal = document.getElementById("pod-preview-modal");
      if (modal && e.target === modal) {
        console.log("[POD Customizer] \u{1F5B1}\uFE0F Click detected on Modal Backdrop.");
        closePreviewModal();
      }
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        const modal = document.getElementById("pod-preview-modal");
        if (modal && modal.style.display === "flex") {
          console.log("[POD Customizer] \u2328\uFE0F Escape key pressed. Closing modal.");
          closePreviewModal();
        }
      }
    });
  }

  // assets/js/src/main.js
  function init() {
    if (typeof window.podCustomizerConfig === "undefined" || typeof window.fabric === "undefined") {
      console.warn("POD Customizer: missing podCustomizerConfig or fabric.js.");
      return;
    }
    initCanvas();
    initPreviewModal();
    if (config.template) {
      initTemplateMode();
    } else {
      renderMockupGrid();
      renderClipartGrid();
      bindGlobalEvents();
      loadInitialLayers();
    }
  }
  function initTemplateMode() {
    const tpl = config.template;
    if (tpl.mockup && tpl.mockup.url) {
      loadMockupImage(tpl.mockup.url);
    }
    initTemplateForm(tpl, state.templateValues, (fieldId, newValue) => {
      state.templateValues[fieldId] = newValue;
      updateTemplateField(fieldId, newValue);
    });
    const fontsPromise = document.fonts ? document.fonts.ready : Promise.resolve();
    fontsPromise.then(() => {
      renderAllTemplateFields();
      if (elements.loading) {
        elements.loading.style.opacity = "0";
        setTimeout(() => elements.loading.style.display = "none", 300);
      }
    });
    if (elements.btnReset) {
      elements.btnReset.addEventListener("click", () => {
        if (Array.isArray(tpl.fields)) {
          tpl.fields.forEach((f) => {
            state.templateValues[f.id] = f.default_value;
          });
          initTemplateForm(tpl, state.templateValues, (fieldId, newValue) => {
            state.templateValues[fieldId] = newValue;
            updateTemplateField(fieldId, newValue);
          });
          renderAllTemplateFields();
        }
      });
    }
    if (elements.addToCartForm) {
      elements.addToCartForm.addEventListener("submit", onAddToCartSubmit);
    }
  }
  function bindGlobalEvents() {
    const tabBtns = document.querySelectorAll(".pod-tab-btn");
    tabBtns.forEach((btn) => {
      btn.addEventListener("click", () => {
        tabBtns.forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        const targetId = btn.dataset.tab;
        document.querySelectorAll(".pod-tab-pane").forEach((p) => p.classList.remove("active"));
        const targetPane = document.getElementById(targetId);
        if (targetPane) targetPane.classList.add("active");
      });
    });
    initTextEvents();
    initClipartEvents();
    initPhotoEvents();
    if (elements.btnReset) {
      elements.btnReset.addEventListener("click", resetStudio);
    }
    if (elements.addToCartForm) {
      elements.addToCartForm.addEventListener("submit", onAddToCartSubmit);
    }
  }
  function loadInitialLayers() {
    if (elements.loading) elements.loading.style.display = "flex";
    if (state.currentMockup) {
      loadMockupImage(state.currentMockup.url);
    }
    if (config.cliparts && config.cliparts[0]) {
      addClipartLayer(config.cliparts[0]);
    }
    addTextLayer("Best Dad Ever", false);
    setTimeout(() => {
      if (elements.loading) {
        elements.loading.style.opacity = "0";
        setTimeout(() => elements.loading.style.display = "none", 300);
      }
    }, 400);
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
