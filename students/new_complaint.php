<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];
$reg_number = $_SESSION['reg_number'];

$categories = [
    'Examination case', 'Accountant', 'Hostel', 'Academic',
    'Infrastructure', 'Service', 'Gender issue', 'Students Government', 'Other'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>New Complaint - IAA CFMS</title>
    <link rel="icon" type="image/png" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7fc;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* Left Panel - Soft blur white/blue */
        .left-panel {
            width: 320px;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            color: #0b2b4b;
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.05);
            z-index: 100;
            padding: 32px 24px;
            border-right: 1px solid rgba(0, 71, 171, 0.1);
            transition: all 0.3s ease;
        }

        .logo-area h2 {
            font-size: 1.8rem;
            background: linear-gradient(135deg, #0047AB, #2a9d8f);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 8px;
        }

        .logo-area p {
            font-size: 0.75rem;
            color: #5a6e8a;
            margin-bottom: 40px;
        }

        .step-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #0047AB;
            margin-bottom: 28px;
            border-left: 3px solid #0047AB;
            padding-left: 12px;
        }

        .step-list {
            display: flex;
            flex-direction: column;
            gap: 0;
            position: relative;
            margin-left: 16px;
        }

        /* Vertical connecting line – will be positioned by JS */
        .step-list::before {
            content: '';
            position: absolute;
            left: 20px; /* center of circle */
            width: 3px;
            background: linear-gradient(to bottom, #0047AB var(--progress), #cbd5e1 var(--progress));
            z-index: 0;
            border-radius: 2px;
            transition: background 0.3s;
        }

        .step-item {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            padding: 24px 12px;
            margin: 8px 0;
            border-radius: 16px;
            cursor: pointer;
            transition: 0.2s;
            position: relative;
            z-index: 1;
            background: transparent;
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: white;
            border: 2px solid #cbd5e1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
            color: #5a6e8a;
            flex-shrink: 0;
            transition: all 0.2s;
        }

        .step-item.active .step-number {
            background: #0047AB;
            border-color: #0047AB;
            color: white;
            box-shadow: 0 0 0 3px rgba(0,71,171,0.2);
        }

        .step-item.completed .step-number {
            background: #2ecc71;
            border-color: #2ecc71;
            color: white;
        }

        .step-item.completed .step-number i {
            font-size: 1rem;
        }

        .step-label {
            font-size: 1rem;
            font-weight: 500;
            color: #1f2c40;
            padding-top: 8px;
        }

        .step-item.active .step-label {
            color: #0047AB;
            font-weight: 600;
            font-size: 1.05rem;
        }

        /* Right Panel */
        .right-panel {
            flex: 1;
            margin-left: 320px;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow-y: auto;
            background: #f4f7fc;
            padding: 40px 60px;
            transition: margin-left 0.3s ease;
        }

        .form-container {
            max-width: 1000px;
            margin: 0 auto;
            width: 100%;
        }

        .step-content {
            display: none;
            background: white;
            border-radius: 32px;
            padding: 40px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 71, 171, 0.08);
        }

        .step-content.active {
            display: block;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 28px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1f2c40;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            font-size: 1rem;
            transition: 0.2s;
            background: #fefefe;
        }
        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: #0047AB;
            box-shadow: 0 0 0 3px rgba(0,71,171,0.08);
        }
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 28px;
        }
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        .character-count {
            text-align: right;
            font-size: 0.75rem;
            color: #6c85a3;
            margin-top: 6px;
        }
        .category-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .category-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 20px 12px;
            text-align: center;
            cursor: pointer;
            transition: 0.2s;
        }
        .category-card:hover {
            border-color: #0047AB;
            background: #f0f4ff;
            transform: translateY(-2px);
        }
        .category-card.selected {
            background: #0047AB;
            color: white;
            border-color: #0047AB;
        }
        .category-card i {
            font-size: 2rem;
            margin-bottom: 12px;
            display: block;
        }
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        .btn {
            padding: 12px 28px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: 0.2s;
            font-size: 0.9rem;
        }
        .btn-primary {
            background: #0047AB;
            color: white;
        }
        .btn-primary:hover {
            background: #003380;
        }
        .btn-secondary {
            background: #e0e7f0;
            color: #1f2c40;
        }
        .btn-secondary:hover {
            background: #cbd5e1;
        }
        .error-message {
            background: #fee2e2;
            color: #b91c1c;
            padding: 12px 16px;
            border-radius: 16px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            border-left: 4px solid #b91c1c;
        }
        .preview-card {
            margin-bottom: 30px;
        }
        .preview-section {
            background: #f8fafc;
            border-radius: 24px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid #e2edf2;
        }
        .preview-section h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #0047AB;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid #e2edf2;
            padding-bottom: 8px;
        }
        .preview-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 12px 8px;
        }
        .preview-grid .label {
            font-weight: 500;
            color: #2c3e66;
        }
        .preview-grid .value {
            color: #1f2c40;
            word-break: break-word;
        }
        .preview-description {
            background: white;
            border-radius: 16px;
            padding: 16px;
            margin-top: 8px;
            border: 1px solid #e2edf2;
            line-height: 1.5;
        }
        .evidence-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #eef2ff;
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 0.85rem;
            color: #0047AB;
        }
        .alert {
            padding: 14px 20px;
            border-radius: 20px;
            margin-bottom: 28px;
            font-weight: 500;
        }
        .alert.error {
            background: #fee2e2;
            color: #b91c1c;
            border-left: 4px solid #b91c1c;
        }
        .alert.success {
            background: #e0f2e9;
            color: #1e7b4c;
            border-left: 4px solid #1e7b4c;
        }
        .file-info {
            font-size: 0.85rem;
            color: #5a6e8a;
            margin-top: 8px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .left-panel {
                width: 80px;
                padding: 20px 12px;
            }
            .logo-area h2 {
                font-size: 1.2rem;
                text-align: center;
            }
            .logo-area p,
            .step-label {
                display: none;
            }
            .step-title {
                display: none;
            }
            .step-list::before {
                left: 20px;
            }
            .step-item {
                padding: 20px 0;
                margin: 4px 0;
                justify-content: center;
            }
            .step-number {
                margin: 0 auto;
            }
            .step-list {
                margin-left: 0;
            }
            .right-panel {
                margin-left: 80px;
                padding: 20px;
            }
            .category-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .step-content {
                padding: 24px;
            }
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            .preview-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
        }
    </style>
</head>
<body>

<div class="left-panel">
    <div class="logo-area">
        <h2>CFMS</h2>
        <p>Complaint Management</p>
    </div>
    <div class="step-title">Create New Complaint</div>
    <div class="step-list" id="stepList">
        <div class="step-item active" data-step="1"><div class="step-number"><span>1</span></div><div class="step-label">Choose Category</div></div>
        <div class="step-item" data-step="2"><div class="step-number"><span>2</span></div><div class="step-label">Complaint Details</div></div>
        <div class="step-item" data-step="3"><div class="step-number"><span>3</span></div><div class="step-label">Supporting Evidence</div></div>
        <div class="step-item" data-step="4"><div class="step-number"><span>4</span></div><div class="step-label">Preview</div></div>
        <div class="step-item" data-step="5"><div class="step-number"><span>5</span></div><div class="step-label">Submit</div></div>
    </div>
</div>

<div class="right-panel">
    <div class="form-container">
        <!-- Step 1 -->
        <div id="step-1" class="step-content active">
            <h3 style="margin-bottom: 24px;">Select Complaint Category</h3>
            <div id="step1Error" class="error-message" style="display:none;"></div>
            <div class="category-grid" id="categoryGrid">
                <?php foreach ($categories as $cat): ?>
                    <div class="category-card" data-category="<?php echo htmlspecialchars($cat); ?>">
                        <i class="fas <?php echo getCategoryIcon($cat); ?>"></i>
                        <span><?php echo htmlspecialchars($cat); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="nav-buttons">
                <a href="student_dashboard.php" class="btn btn-secondary">Cancel</a>
                <button id="nextStep1" class="btn btn-primary" disabled>Next</button>
            </div>
        </div>

        <!-- Step 2 -->
        <div id="step-2" class="step-content">
            <h3 style="margin-bottom: 24px;">Complaint Details</h3>
            <div id="step2Error" class="error-message" style="display:none;"></div>
            <div class="form-group">
                <label>Title *</label>
                <input type="text" id="complaintTitle" placeholder="Brief title of your complaint" maxlength="200">
            </div>
            <div class="form-group">
                <label>Description * (max 500 characters)</label>
                <textarea id="complaintDesc" rows="5" maxlength="500" placeholder="Describe your complaint in detail..."></textarea>
                <div class="character-count"><span id="descCount">0</span>/500 characters</div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Location (optional)</label>
                    <input type="text" id="complaintLocation" placeholder="e.g., Hostel Block C, Room 12">
                </div>
                <div class="form-group">
                    <label>Date incident occurred *</label>
                    <input type="date" id="incidentDate">
                </div>
            </div>
            <div class="form-group">
                <label>Priority *</label>
                <select id="priority">
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="low">Low</option>
                </select>
            </div>
            <div class="nav-buttons">
                <button id="prevStep2" class="btn btn-secondary">Back</button>
                <button id="nextStep2" class="btn btn-primary">Next</button>
            </div>
        </div>

        <!-- Step 3 -->
        <div id="step-3" class="step-content">
            <h3 style="margin-bottom: 24px;">Supporting Evidence</h3>
            <div id="step3Error" class="error-message" style="display:none;"></div>
            <div class="form-group">
                <label>Upload evidence (optional)</label>
                <input type="file" id="evidenceFile" accept="image/jpeg,image/png,image/jpg,application/pdf,video/mp4,audio/mpeg">
                <div class="file-info">Allowed: JPG, PNG, PDF, MP4, MP3 (max 10MB)</div>
                <div id="fileNameDisplay" style="margin-top: 12px; font-size: 0.85rem; color: #2ecc71;"></div>
            </div>
            <div class="nav-buttons">
                <button id="prevStep3" class="btn btn-secondary">Back</button>
                <button id="nextStep3" class="btn btn-primary">Next</button>
            </div>
        </div>

        <!-- Step 4 -->
        <div id="step-4" class="step-content">
            <h3 style="margin-bottom: 24px;">Review Your Complaint</h3>
            <div id="step4Error" class="error-message" style="display:none;"></div>
            <div class="preview-card">
                <div class="preview-section">
                    <h4><i class="fas fa-info-circle"></i> Overview</h4>
                    <div class="preview-grid">
                        <div class="label">Category:</div><div class="value" id="previewCategory"></div>
                        <div class="label">Priority:</div><div class="value" id="previewPriority"></div>
                        <div class="label">Incident Date:</div><div class="value" id="previewDate"></div>
                        <div class="label">Location:</div><div class="value" id="previewLocation"></div>
                    </div>
                </div>
                <div class="preview-section">
                    <h4><i class="fas fa-heading"></i> Title</h4>
                    <div id="previewTitle" class="value" style="font-weight:500;"></div>
                </div>
                <div class="preview-section">
                    <h4><i class="fas fa-align-left"></i> Description</h4>
                    <div id="previewDesc" class="preview-description"></div>
                </div>
                <div class="preview-section">
                    <h4><i class="fas fa-paperclip"></i> Supporting Evidence</h4>
                    <div id="previewEvidence" class="evidence-badge"><i class="fas fa-file-alt"></i> None</div>
                </div>
            </div>
            <div class="nav-buttons">
                <button id="prevStep4" class="btn btn-secondary">Back to Edit</button>
                <button id="nextStep4" class="btn btn-primary">Proceed to Submit</button>
            </div>
        </div>

        <!-- Step 5 -->
        <div id="step-5" class="step-content">
            <h3 style="margin-bottom: 24px;">Final Submission</h3>
            <div id="submitMessage" class="alert" style="display:none;"></div>
            <div class="preview-card">
                <div class="preview-section">
                    <h4><i class="fas fa-check-circle"></i> Confirm Details</h4>
                    <div class="preview-grid">
                        <div class="label">Category:</div><div class="value" id="finalCategory"></div>
                        <div class="label">Title:</div><div class="value" id="finalTitle"></div>
                        <div class="label">Priority:</div><div class="value" id="finalPriority"></div>
                        <div class="label">Incident Date:</div><div class="value" id="finalDate"></div>
                        <div class="label">Location:</div><div class="value" id="finalLocation"></div>
                        <div class="label">Evidence:</div><div class="value" id="finalEvidence"></div>
                    </div>
                    <div style="margin-top:16px;"><strong>Description:</strong><br><span id="finalDesc" style="color:#2c3e66;"></span></div>
                </div>
            </div>
            <div class="nav-buttons">
                <button id="prevStep5" class="btn btn-secondary">Back</button>
                <button id="submitComplaint" class="btn btn-primary">Submit Complaint</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Global state
    let currentStep = 1;
    let selectedCategory = '';
    let complaintTitle = '';
    let complaintDesc = '';
    let complaintLocation = '';
    let incidentDate = '';
    let priority = 'medium';
    let selectedFile = null;

    // DOM elements
    const stepItems = document.querySelectorAll('.step-item');
    const stepContents = document.querySelectorAll('.step-content');
    const categoryCards = document.querySelectorAll('.category-card');
    const nextStep1 = document.getElementById('nextStep1');
    const nextStep2 = document.getElementById('nextStep2');
    const nextStep3 = document.getElementById('nextStep3');
    const nextStep4 = document.getElementById('nextStep4');
    const prevStep2 = document.getElementById('prevStep2');
    const prevStep3 = document.getElementById('prevStep3');
    const prevStep4 = document.getElementById('prevStep4');
    const prevStep5 = document.getElementById('prevStep5');
    const submitBtn = document.getElementById('submitComplaint');
    const titleInput = document.getElementById('complaintTitle');
    const descInput = document.getElementById('complaintDesc');
    const locationInput = document.getElementById('complaintLocation');
    const dateInput = document.getElementById('incidentDate');
    const prioritySelect = document.getElementById('priority');
    const evidenceFile = document.getElementById('evidenceFile');
    const fileNameDisplay = document.getElementById('fileNameDisplay');
    const descCountSpan = document.getElementById('descCount');

    // Error containers
    const step1Error = document.getElementById('step1Error');
    const step2Error = document.getElementById('step2Error');
    const step3Error = document.getElementById('step3Error');
    const step4Error = document.getElementById('step4Error');
    const submitMessage = document.getElementById('submitMessage');

    // Preview & final elements
    const previewCategory = document.getElementById('previewCategory');
    const previewTitle = document.getElementById('previewTitle');
    const previewDesc = document.getElementById('previewDesc');
    const previewLocation = document.getElementById('previewLocation');
    const previewDate = document.getElementById('previewDate');
    const previewPriority = document.getElementById('previewPriority');
    const previewEvidence = document.getElementById('previewEvidence');
    const finalCategory = document.getElementById('finalCategory');
    const finalTitle = document.getElementById('finalTitle');
    const finalDesc = document.getElementById('finalDesc');
    const finalLocation = document.getElementById('finalLocation');
    const finalDate = document.getElementById('finalDate');
    const finalPriority = document.getElementById('finalPriority');
    const finalEvidence = document.getElementById('finalEvidence');
    const stepList = document.getElementById('stepList');

    // Helper: show/hide inline error
    function showError(element, message) {
        element.innerText = message;
        element.style.display = 'block';
        setTimeout(() => {
            element.style.display = 'none';
        }, 5000);
    }
    function clearError(element) {
        element.style.display = 'none';
    }

    // Update step UI (active/completed) and progress line
    function updateSteps() {
        stepContents.forEach((content, idx) => { content.classList.toggle('active', idx + 1 === currentStep); });
        stepItems.forEach((item, idx) => {
            const stepNum = idx + 1;
            const numberDiv = item.querySelector('.step-number');
            if (stepNum === currentStep) {
                item.classList.add('active'); item.classList.remove('completed');
                numberDiv.innerHTML = `<span>${stepNum}</span>`;
            } else if (stepNum < currentStep) {
                item.classList.add('completed'); item.classList.remove('active');
                numberDiv.innerHTML = `<i class="fas fa-check"></i>`;
            } else {
                item.classList.remove('active', 'completed');
                numberDiv.innerHTML = `<span>${stepNum}</span>`;
            }
        });
        const totalSteps = 5;
        const completedSteps = currentStep - 1;
        const progressPercent = (completedSteps / (totalSteps - 1)) * 100;
        stepList.style.setProperty('--progress', `${progressPercent}%`);
        // Adjust the vertical line position after each update
        positionVerticalLine();
    }

    // Dynamically position the vertical connecting line between the first and last step circles
    function positionVerticalLine() {
        const firstStep = stepItems[0];
        const lastStep = stepItems[stepItems.length - 1];
        if (firstStep && lastStep) {
            const firstCircle = firstStep.querySelector('.step-number');
            const lastCircle = lastStep.querySelector('.step-number');
            if (firstCircle && lastCircle) {
                const firstRect = firstCircle.getBoundingClientRect();
                const lastRect = lastCircle.getBoundingClientRect();
                const containerRect = stepList.getBoundingClientRect();
                const topOffset = firstRect.top + firstRect.height / 2 - containerRect.top;
                const bottomOffset = containerRect.bottom - (lastRect.top + lastRect.height / 2);
                stepList.style.setProperty('--line-top', `${topOffset}px`);
                stepList.style.setProperty('--line-bottom', `${bottomOffset}px`);
                // Apply to pseudo-element
                const style = document.createElement('style');
                style.id = 'dynamic-line-style';
                const oldStyle = document.getElementById('dynamic-line-style');
                if (oldStyle) oldStyle.remove();
                style.id = 'dynamic-line-style';
                style.textContent = `.step-list::before { top: var(--line-top); bottom: var(--line-bottom); }`;
                document.head.appendChild(style);
            }
        }
    }

    // Step 1: Category
    categoryCards.forEach(card => {
        card.addEventListener('click', () => {
            categoryCards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            selectedCategory = card.getAttribute('data-category');
            nextStep1.disabled = false;
            clearError(step1Error);
        });
    });
    nextStep1.addEventListener('click', () => {
        if (!selectedCategory) {
            showError(step1Error, 'Please select a complaint category.');
            return;
        }
        currentStep = 2;
        updateSteps();
    });

    // Description counter
    descInput.addEventListener('input', () => { descCountSpan.innerText = descInput.value.length; });

    // Step 2 validation
    nextStep2.addEventListener('click', () => {
        let title = titleInput.value.trim();
        let desc = descInput.value.trim();
        let date = dateInput.value;
        if (!title) { showError(step2Error, 'Please enter a title.'); return; }
        if (!desc) { showError(step2Error, 'Please enter a description.'); return; }
        if (!date) { showError(step2Error, 'Please select the incident date.'); return; }
        clearError(step2Error);
        complaintTitle = title;
        complaintDesc = desc;
        complaintLocation = locationInput.value.trim();
        incidentDate = date;
        priority = prioritySelect.value;
        updatePreviewAndFinal();
        currentStep = 3;
        updateSteps();
    });
    prevStep2.addEventListener('click', () => {
        currentStep = 1;
        updateSteps();
    });

    // Step 3
    evidenceFile.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            selectedFile = e.target.files[0];
            fileNameDisplay.innerText = `Selected: ${selectedFile.name}`;
        } else {
            selectedFile = null;
            fileNameDisplay.innerText = '';
        }
        updatePreviewAndFinal();
    });
    nextStep3.addEventListener('click', () => {
        currentStep = 4;
        updateSteps();
    });
    prevStep3.addEventListener('click', () => {
        currentStep = 2;
        updateSteps();
    });

    function updatePreviewAndFinal() {
        const evidenceName = selectedFile ? selectedFile.name : 'None';
        previewCategory.innerText = selectedCategory || 'Not selected';
        previewTitle.innerText = complaintTitle || '(empty)';
        previewDesc.innerText = complaintDesc || '(empty)';
        previewLocation.innerText = complaintLocation || 'Not provided';
        previewDate.innerText = incidentDate || 'Not set';
        previewPriority.innerText = priority.charAt(0).toUpperCase() + priority.slice(1);
        previewEvidence.innerHTML = evidenceName === 'None' ? '<i class="fas fa-file-alt"></i> None' : `<i class="fas fa-paperclip"></i> ${evidenceName}`;
        finalCategory.innerText = selectedCategory || 'Not selected';
        finalTitle.innerText = complaintTitle || '(empty)';
        finalDesc.innerText = complaintDesc || '(empty)';
        finalLocation.innerText = complaintLocation || 'Not provided';
        finalDate.innerText = incidentDate || 'Not set';
        finalPriority.innerText = priority.charAt(0).toUpperCase() + priority.slice(1);
        finalEvidence.innerText = evidenceName;
    }

    nextStep4.addEventListener('click', () => {
        if (!complaintTitle || !complaintDesc) {
            showError(step4Error, 'Please complete the complaint details first (Step 2).');
            currentStep = 2;
            updateSteps();
            return;
        }
        currentStep = 5;
        updateSteps();
    });
    prevStep4.addEventListener('click', () => {
        currentStep = 3;
        updateSteps();
    });
    prevStep5.addEventListener('click', () => {
        currentStep = 4;
        updateSteps();
    });

    // Submit via AJAX
    submitBtn.addEventListener('click', async () => {
        const formData = new FormData();
        formData.append('category', selectedCategory);
        formData.append('title', complaintTitle);
        formData.append('description', complaintDesc);
        formData.append('location', complaintLocation);
        formData.append('incident_date', incidentDate);
        formData.append('priority', priority);
        if (selectedFile) formData.append('evidence', selectedFile);
        submitMessage.style.display = 'block';
        submitMessage.className = 'alert';
        submitMessage.innerHTML = 'Submitting, please wait...';
        submitBtn.disabled = true;
        try {
            const response = await fetch('submit_complaint.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                submitMessage.className = 'alert success';
                submitMessage.innerHTML = '✓ Complaint submitted successfully! Redirecting to dashboard...';
                setTimeout(() => { window.location.href = 'student_dashboard.php'; }, 2000);
            } else {
                submitMessage.className = 'alert error';
                submitMessage.innerHTML = 'Error: ' + result.message;
                submitBtn.disabled = false;
            }
        } catch (err) {
            submitMessage.className = 'alert error';
            submitMessage.innerHTML = 'Network error. Please try again.';
            submitBtn.disabled = false;
        }
    });

    // Initial positioning and listen to resize
    window.addEventListener('resize', () => { positionVerticalLine(); });
    // Also position after images load and after any step height change (e.g., when step content changes)
    const observer = new ResizeObserver(() => positionVerticalLine());
    observer.observe(stepList);
    updateSteps();
</script>
<?php
function getCategoryIcon($category) {
    switch ($category) {
        case 'Examination case': return 'fa-file-excel';
        case 'Accountant': return 'fa-calculator';
        case 'Hostel': return 'fa-home';
        case 'Academic': return 'fa-chalkboard-user';
        case 'Infrastructure': return 'fa-building';
        case 'Service': return 'fa-concierge-bell';
        case 'Gender issue': return 'fa-venus-mars';
        case 'Students Government': return 'fa-users';
        default: return 'fa-ticket-alt';
    }
}
?>
</body>
</html>