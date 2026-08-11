/**
 * 교회 스마트 비용지출요청서 - Senior Easy Interactive JavaScript (UTF-8)
 */

document.addEventListener('DOMContentLoaded', function () {
    initTableCalculations();
    initSignatureCanvas();
    initReceiptPreview();
});

// 1. 실시간 비용 자동 합산 계산기
function initTableCalculations() {
    const tableBody = document.querySelector('#items-table-body');
    if (!tableBody) return;

    tableBody.addEventListener('input', function (e) {
        if (e.target.classList.contains('item-amount')) {
            calculateTotal();
        }
    });

    calculateTotal(); // 최초 1회 계산
}

function calculateTotal() {
    const amountInputs = document.querySelectorAll('.item-amount');
    let total = 0;

    amountInputs.forEach(input => {
        const val = parseInt(input.value.replace(/[^0-9]/g, ''), 10) || 0;
        total += val;
    });

    const totalDisplay = document.getElementById('total-amount-display');
    const totalHidden = document.getElementById('total_amount');

    if (totalDisplay) {
        totalDisplay.textContent = total.toLocaleString('ko-KR') + ' 원';
    }
    if (totalHidden) {
        totalHidden.value = total;
    }
}

// 2. 지출 항목 행 추가/삭제
function addRow() {
    const tableBody = document.getElementById('items-table-body');
    if (!tableBody) return;

    const rowCount = tableBody.children.length + 1;
    const tr = document.createElement('tr');

    tr.innerHTML = `
        <td style="text-align: center; font-weight: bold;">${rowCount}</td>
        <td><input type="text" name="item_name[]" class="form-control" placeholder="구매 항목/내역" required></td>
        <td><input type="number" name="amount[]" class="form-control item-amount" placeholder="비용(원)" required min="0"></td>
        <td><input type="text" name="note[]" class="form-control" placeholder="비고"></td>
        <td style="text-align: center;">
            <button type="button" class="btn-danger-sm" onclick="removeRow(this)">✕</button>
        </td>
    `;

    tableBody.appendChild(tr);
    calculateTotal();
}

function removeRow(btn) {
    const tableBody = document.getElementById('items-table-body');
    if (tableBody.children.length <= 1) {
        alert('최소 1개 이상의 지출 항목이 필요합니다.');
        return;
    }
    const tr = btn.closest('tr');
    tr.remove();

    // 행 번호 재정렬
    Array.from(tableBody.children).forEach((row, idx) => {
        row.children[0].textContent = idx + 1;
    });

    calculateTotal();
}

// 3. 전자서명 Canvas 터치 패드 구현
function initSignatureCanvas() {
    const canvas = document.getElementById('sig-canvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    let isDrawing = false;

    // 캔버스 고해상도 리사이즈
    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = rect.height || 150;
        ctx.lineWidth = 3;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#1e3a8a';
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: clientX - rect.left,
            y: clientY - rect.top
        };
    }

    function startDraw(e) {
        isDrawing = true;
        const pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
    }

    function draw(e) {
        if (!isDrawing) return;
        e.preventDefault();
        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    }

    function stopDraw() {
        if (isDrawing) {
            isDrawing = false;
            saveSignatureData();
        }
    }

    // 마우스 이벤트
    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDraw);
    canvas.addEventListener('mouseleave', stopDraw);

    // 터치 이벤트 (모바일/스마트폰 전용)
    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', stopDraw);

    // 서명 초기화 버튼
    const clearBtn = document.getElementById('clear-sig-btn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            saveSignatureData();
        });
    }

    function saveSignatureData() {
        const sigDataInput = document.getElementById('signature_data');
        if (sigDataInput) {
            sigDataInput.value = canvas.toDataURL('image/png');
        }
    }
}

// 4. 영수증 이미지 카메라 촬영 및 미리보기
function initReceiptPreview() {
    const fileInput = document.getElementById('receipt-input');
    const previewContainer = document.getElementById('receipt-preview-container');

    if (!fileInput || !previewContainer) return;

    fileInput.addEventListener('change', function () {
        previewContainer.innerHTML = '';
        const files = this.files;

        if (files && files.length > 0) {
            Array.from(files).forEach(file => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'receipt-preview-img';
                        previewContainer.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    });
}
