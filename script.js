document.addEventListener('DOMContentLoaded', () => {
    // Abas
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    // Elementos da UI - Upload
    const uploadForm = document.getElementById('uploadForm');
    const imageInput = document.getElementById('imageInput');
    const dropZone = document.getElementById('dropZone');
    const uploadPlaceholder = document.getElementById('uploadPlaceholder');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const imagePreview = document.getElementById('imagePreview');
    const btnRemoveFile = document.getElementById('btnRemoveFile');
    const btnSubmit = document.getElementById('btnSubmit');
    
    const progressContainer = document.getElementById('progressContainer');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    
    const resultContainer = document.getElementById('resultContainer');
    const btnNewUpload = document.getElementById('btnNewUpload');
    const btnViewGallery = document.getElementById('btnViewGallery');
    const toastEl = document.getElementById('toast');

    // Elementos da UI - Galeria
    const galleryGrid = document.getElementById('gallery-grid');
    const galleryLoader = document.getElementById('gallery-loader');
    const emptyState = document.getElementById('empty-state');

    // Estado global
    let selectedFile = null;
    const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    const ALLOWED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    // Lógica das Abas
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.getAttribute('data-tab');
            
            // Remover active de todos
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.add('hidden'));
            
            // Adicionar active no clicado
            btn.classList.add('active');
            document.getElementById(tabId).classList.remove('hidden');
            document.getElementById(tabId).classList.add('active');

            if (tabId === 'gallery-tab') {
                loadGallery();
            }
        });
    });

    // Funções Utilitárias
    function showToast(message, type = 'success') {
        toastEl.textContent = message;
        toastEl.className = `toast ${type} show`;
        setTimeout(() => toastEl.classList.remove('show'), 3000);
    }

    function resetForm() {
        uploadForm.reset();
        selectedFile = null;
        imagePreviewContainer.classList.add('hidden');
        uploadPlaceholder.classList.remove('hidden');
        resultContainer.classList.add('hidden');
        uploadForm.classList.remove('hidden');
        btnSubmit.disabled = false;
        progressFill.style.width = '0%';
    }

    function handleFileSelection(file) {
        if (!file) return;

        if (!ALLOWED_TYPES.includes(file.type)) {
            showToast('Formato inválido. Use JPG, PNG ou WEBP.', 'error');
            resetForm();
            return;
        }

        if (file.size > MAX_FILE_SIZE) {
            showToast('Arquivo muito grande. Máximo de 5MB.', 'error');
            resetForm();
            return;
        }

        selectedFile = file;

        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.src = e.target.result;
            uploadPlaceholder.classList.add('hidden');
            imagePreviewContainer.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }

    // Drag & Drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, e => {
            e.preventDefault();
            e.stopPropagation();
        }, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
    });

    dropZone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            imageInput.files = files;
            handleFileSelection(files[0]);
        }
    });

    imageInput.addEventListener('change', function() {
        if (this.files && this.files[0]) handleFileSelection(this.files[0]);
    });

    btnRemoveFile.addEventListener('click', (e) => {
        e.stopPropagation();
        resetForm();
    });

    // Função de Upload
    function uploadFile(formData) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'upload.php', true);
            
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressFill.style.width = percent + '%';
                    progressText.textContent = `Enviando... ${percent}%`;
                }
            };
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const res = JSON.parse(xhr.responseText);
                        resolve(res);
                    } catch (err) {
                        reject(new Error('Erro ao processar resposta do servidor.'));
                    }
                } else {
                    reject(new Error('Erro no servidor: ' + xhr.status));
                }
            };
            
            xhr.onerror = () => reject(new Error('Erro de conexão ao enviar o arquivo.'));
            xhr.send(formData);
        });
    }

    uploadForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!selectedFile) {
            showToast('Selecione uma imagem primeiro.', 'error');
            return;
        }

        const formData = new FormData(uploadForm);
        
        btnSubmit.disabled = true;
        uploadForm.classList.add('hidden');
        progressContainer.classList.remove('hidden');
        progressFill.style.width = '0%';
        progressText.textContent = 'Iniciando upload...';

        try {
            const response = await uploadFile(formData);

            if (response.status === 'success') {
                progressContainer.classList.add('hidden');
                resultContainer.classList.remove('hidden');
                
                if(response.db_warning) {
                    showToast(response.db_warning, 'error');
                } else {
                    showToast('Upload realizado com sucesso!', 'success');
                }
            } else {
                throw new Error(response.message || 'Erro desconhecido.');
            }
        } catch (error) {
            console.error('Erro de upload:', error);
            progressContainer.classList.add('hidden');
            uploadForm.classList.remove('hidden');
            btnSubmit.disabled = false;
            showToast(error.message, 'error');
        }
    });

    btnNewUpload.addEventListener('click', resetForm);
    
    btnViewGallery.addEventListener('click', () => {
        resetForm();
        document.querySelector('[data-tab="gallery-tab"]').click();
    });

    // ==========================================
    // LÓGICA DA GALERIA
    // ==========================================
    
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    async function loadGallery() {
        galleryGrid.innerHTML = '';
        emptyState.classList.add('hidden');
        galleryLoader.classList.remove('hidden');

        try {
            const res = await fetch('list.php');
            const data = await res.json();
            
            galleryLoader.classList.add('hidden');

            if (data.status === 'error') {
                throw new Error(data.message);
            }

            if (!Array.isArray(data) || data.length === 0) {
                emptyState.classList.remove('hidden');
                return;
            }

            // Renderizar cards
            data.forEach(img => {
                const card = document.createElement('div');
                card.className = 'gallery-card';
                
                const descText = img.description ? img.description : 'Sem descrição';
                
                card.innerHTML = `
                    <img src="${img.url}" alt="${descText}" class="gallery-card-img" loading="lazy">
                    <div class="gallery-card-content">
                        <p class="gallery-card-desc" title="${descText}">${descText}</p>
                        <span class="gallery-card-date">${formatDate(img.created_at)}</span>
                        
                        <div class="gallery-card-actions">
                            <button type="button" class="btn-copy-small" onclick="copyToClipboard('${img.url}')">
                                <i class="ph ph-copy"></i> Copiar Link
                            </button>
                        </div>
                    </div>
                `;
                
                galleryGrid.appendChild(card);
            });

        } catch (err) {
            console.error(err);
            galleryLoader.classList.add('hidden');
            showToast('Erro ao carregar a galeria', 'error');
        }
    }

    // Função global para copiar url
    window.copyToClipboard = function(text) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Link copiado!', 'success');
        }).catch(() => {
            showToast('Erro ao copiar link', 'error');
        });
    };
});
