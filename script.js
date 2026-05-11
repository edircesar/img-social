document.addEventListener('DOMContentLoaded', () => {
    // Elementos da UI
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
    const resultImage = document.getElementById('resultImage');
    const resultDesc = document.getElementById('resultDesc');
    const resultUrl = document.getElementById('resultUrl');
    const btnCopy = document.getElementById('btnCopy');
    const btnNewUpload = document.getElementById('btnNewUpload');
    const descGroup = document.getElementById('descGroup');
    const toastEl = document.getElementById('toast');

    // Variável para armazenar o arquivo selecionado
    let selectedFile = null;

    // Constantes
    const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB em bytes
    const ALLOWED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    // Funções de UI
    function showToast(message, type = 'success') {
        toastEl.textContent = message;
        toastEl.className = `toast ${type} show`;
        
        setTimeout(() => {
            toastEl.classList.remove('show');
        }, 3000);
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

        // Validação de tipo
        if (!ALLOWED_TYPES.includes(file.type)) {
            showToast('Formato inválido. Use JPG, PNG ou WEBP.', 'error');
            resetForm();
            return;
        }

        // Validação de tamanho
        if (file.size > MAX_FILE_SIZE) {
            showToast('Arquivo muito grande. Máximo de 5MB.', 'error');
            resetForm();
            return;
        }

        selectedFile = file;

        // Mostrar preview
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.src = e.target.result;
            uploadPlaceholder.classList.add('hidden');
            imagePreviewContainer.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }

    // Eventos de Drag & Drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
    });

    dropZone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            imageInput.files = files; // Atualiza o input file
            handleFileSelection(files[0]);
        }
    });

    // Evento de Input (Clique)
    imageInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            handleFileSelection(this.files[0]);
        }
    });

    // Evento Remover Preview
    btnRemoveFile.addEventListener('click', (e) => {
        e.stopPropagation(); // Evita reabrir o seletor de arquivos
        resetForm();
    });

    // Função de Upload com XMLHttpRequest para Barra de Progresso Real
    function uploadFile(formData) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            
            xhr.open('POST', 'upload.php', true);
            
            // Monitorar progresso do upload
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 100);
                    progressFill.style.width = percentComplete + '%';
                    progressText.textContent = `Enviando... ${percentComplete}%`;
                }
            };
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (err) {
                        reject(new Error('Erro ao processar resposta do servidor.'));
                    }
                } else {
                    reject(new Error('Erro no servidor: ' + xhr.status));
                }
            };
            
            xhr.onerror = function() {
                reject(new Error('Erro de conexão ao tentar fazer o upload.'));
            };
            
            xhr.send(formData);
        });
    }

    // Evento Submit do Formulário
    uploadForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!selectedFile) {
            showToast('Por favor, selecione uma imagem.', 'error');
            return;
        }

        const formData = new FormData(uploadForm);
        
        // Mudar UI para Loading
        btnSubmit.disabled = true;
        uploadForm.classList.add('hidden');
        progressContainer.classList.remove('hidden');
        progressFill.style.width = '0%';
        progressText.textContent = 'Iniciando...';

        try {
            const response = await uploadFile(formData);

            if (response.status === 'success') {
                // Sucesso: Mostrar Resultado
                progressContainer.classList.add('hidden');
                resultContainer.classList.remove('hidden');
                
                resultImage.src = response.url;
                resultUrl.value = response.url;
                
                const desc = document.getElementById('description').value;
                if (desc.trim() !== '') {
                    resultDesc.textContent = desc;
                    descGroup.classList.remove('hidden');
                } else {
                    descGroup.classList.add('hidden');
                }

                showToast('Upload realizado com sucesso!', 'success');
            } else {
                throw new Error(response.message || 'Erro desconhecido no upload.');
            }

        } catch (error) {
            // Erro: Voltar ao Formulário
            console.error('Erro de upload:', error);
            progressContainer.classList.add('hidden');
            uploadForm.classList.remove('hidden');
            btnSubmit.disabled = false;
            showToast(error.message, 'error');
        }
    });

    // Copiar URL
    btnCopy.addEventListener('click', () => {
        resultUrl.select();
        resultUrl.setSelectionRange(0, 99999); // Para mobile
        
        navigator.clipboard.writeText(resultUrl.value).then(() => {
            showToast('URL copiada para a área de transferência!', 'success');
        }).catch(err => {
            console.error('Erro ao copiar', err);
            showToast('Não foi possível copiar a URL.', 'error');
        });
    });

    // Novo Upload
    btnNewUpload.addEventListener('click', resetForm);
});
