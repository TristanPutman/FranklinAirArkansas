// Franklin Air Arkansas - Order Wizard
// Multi-step form logic, validation, dynamic fields, and pricing calculator

(function () {
    'use strict';

    // --- Pricing (mirrors includes/pricing.php) ---
    // Per-sqft pricing with minimums (all in cents)
    var PRICING = {
        manual_j:    { perSqft: 15, min: 35000, label: 'Manual J Load Calculation' },
        manual_jd:   { perSqft: 35, min: 35000, label: 'Manual J & D' },
        manual_jds:  { perSqft: 50, min: 35000, label: 'Manual J, D, & S' },
        rescheck:    { perSqft: 0,  min: 17000, label: 'REScheck Energy Calculation' },
        commercial:  { perSqft: 0,  min: 0,     label: 'Commercial HVAC Reports' }
    };
    var RUSH_FEE = 7500;

    // --- State ---
    var currentStep = 1;
    var totalSteps = 5;
    var uploadedFiles = [];

    // --- DOM helpers ---
    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    function show(el) { if (el) el.style.display = ''; }
    function hide(el) { if (el) el.style.display = 'none'; }

    // --- Step navigation ---
    function goToStep(step) {
        if (step < 1 || step > totalSteps) return;

        // Validate current step before advancing
        if (step > currentStep && !validateStep(currentStep)) return;

        currentStep = step;

        // Update step panels
        $$('.wizard-step').forEach(function (panel) {
            panel.classList.toggle('active', parseInt(panel.dataset.step) === step);
        });

        // Update progress indicators
        $$('.progress-step').forEach(function (dot) {
            var s = parseInt(dot.dataset.step);
            dot.classList.toggle('active', s === step);
            dot.classList.toggle('completed', s < step);
        });

        // Update buttons
        var prevBtn = $('#wizardPrev');
        var nextBtn = $('#wizardNext');
        var submitBtn = $('#wizardSubmit');

        if (prevBtn) prevBtn.style.display = step === 1 ? 'none' : '';
        if (nextBtn) nextBtn.style.display = step === totalSteps ? 'none' : '';
        if (submitBtn) submitBtn.style.display = step === totalSteps ? '' : 'none';

        // Update review on last step
        if (step === totalSteps) buildReview();

        // Scroll to top of wizard
        var wizard = $('.wizard');
        if (wizard) wizard.scrollIntoView({ behavior: 'smooth', block: 'start' });

        updatePrice();
    }

    // --- Validation ---
    function validateStep(step) {
        clearErrors();
        var errors = [];

        switch (step) {
            case 1:
                if (!getSelectedService()) {
                    errors.push({ field: 'service_type', msg: 'Please select a service.' });
                }
                break;

            case 2:
                var requiredFields = [
                    { name: 'customer_name', msg: 'Client name is required.' },
                    { name: 'customer_email', msg: 'Email is required.' },
                    { name: 'customer_phone', msg: 'Phone number is required.' },
                    { name: 'project_type', msg: 'Please select a project type.' },
                    { name: 'address_street', msg: 'Street address is required.' },
                    { name: 'address_city', msg: 'City is required.' },
                    { name: 'address_state', msg: 'State is required.' },
                    { name: 'address_zip', msg: 'ZIP code is required.' },
                    { name: 'sqft', msg: 'Square footage is required.' },
                    { name: 'front_door_faces', msg: 'Front door direction is required.' }
                ];
                requiredFields.forEach(function (f) {
                    var el = $('[name="' + f.name + '"]');
                    if (el && !el.value.trim()) {
                        errors.push({ field: f.name, msg: f.msg });
                    }
                });
                // Validate email format
                var emailEl = $('[name="customer_email"]');
                if (emailEl && emailEl.value.trim() && !isValidEmail(emailEl.value)) {
                    errors.push({ field: 'customer_email', msg: 'A valid email is required.' });
                }
                break;

            case 3:
                // Building details are mostly optional; no hard validation
                break;

            case 4:
                // File upload optional ("I'll email plans later" is fine)
                break;

            case 5:
                var termsEl = $('[name="terms"]');
                if (!termsEl.checked) errors.push({ field: 'terms', msg: 'Please agree to the terms.' });
                break;
        }

        if (errors.length) {
            showErrors(errors);
            return false;
        }
        return true;
    }

    function clearErrors() {
        $$('.field-error').forEach(function (el) { el.remove(); });
        $$('.has-error').forEach(function (el) { el.classList.remove('has-error'); });
    }

    function showErrors(errors) {
        errors.forEach(function (err) {
            var field = $('[name="' + err.field + '"]');
            if (!field) return;
            var group = field.closest('.form-group') || field.parentElement;
            if (group) {
                group.classList.add('has-error');
                var errEl = document.createElement('div');
                errEl.className = 'field-error';
                errEl.textContent = err.msg;
                group.appendChild(errEl);
            }
        });
        // Focus first error field
        var firstErr = errors[0];
        if (firstErr) {
            var el = $('[name="' + firstErr.field + '"]');
            if (el && el.focus) el.focus();
        }
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // --- Service selection ---
    function getSelectedService() {
        var checked = $('input[name="service_type"]:checked');
        return checked ? checked.value : null;
    }

    function getSqft() {
        var el = $('[name="sqft"]');
        return el ? parseInt(el.value) || 0 : 0;
    }

    function isRush() {
        var el = $('[name="rush"]');
        return el ? el.checked : false;
    }

    // --- Pricing calculator ---
    function updatePrice() {
        var service = getSelectedService();
        var priceEl = $('#priceDisplay');
        var breakdownEl = $('#priceBreakdown');
        var noteEl = $('#priceNote');
        if (!priceEl) return;

        if (!service || service === 'commercial') {
            priceEl.textContent = service === 'commercial' ? 'Call for Quote' : '$0';
            if (breakdownEl) breakdownEl.innerHTML = service === 'commercial' ? '<span class="price-note">Commercial projects are quoted individually.</span>' : '';
            if (noteEl) noteEl.style.display = (!service || service === 'commercial') ? '' : 'none';
            return;
        }

        var p = PRICING[service];
        if (!p) return;

        var sqft = getSqft();
        var rush = isRush();
        var lines = [];

        // For rescheck, flat rate
        if (service === 'rescheck') {
            var total = p.min;
            lines.push('<span class="breakdown-line"><span>' + p.label + '</span><span>' + formatCents(p.min) + '</span></span>');
            if (rush) {
                total += RUSH_FEE;
                lines.push('<span class="breakdown-line rush-line"><span>Rush service</span><span>+' + formatCents(RUSH_FEE) + '</span></span>');
            }
            priceEl.textContent = formatCents(total);
            if (breakdownEl) breakdownEl.innerHTML = lines.join('') + '<span class="breakdown-total"><span>Total</span><span>' + formatCents(total) + '</span></span>';
            if (noteEl) noteEl.style.display = 'none';
            return;
        }

        // Per-sqft with minimum
        if (sqft === 0) {
            priceEl.textContent = formatCents(p.min) + '+';
            if (breakdownEl) breakdownEl.innerHTML = '<span class="breakdown-line"><span>' + p.label + ' ($' + (p.perSqft / 100).toFixed(2) + '/sqft)</span><span>' + formatCents(p.min) + ' min</span></span>';
            if (noteEl) { noteEl.textContent = 'Enter square footage in Step 2 for exact price'; noteEl.style.display = ''; }
            return;
        }

        var sqftTotal = p.perSqft * sqft;
        var base = Math.max(sqftTotal, p.min);
        var total = base;

        if (sqftTotal >= p.min) {
            lines.push('<span class="breakdown-line"><span>' + sqft.toLocaleString() + ' sqft × $' + (p.perSqft / 100).toFixed(2) + '</span><span>' + formatCents(sqftTotal) + '</span></span>');
        } else {
            lines.push('<span class="breakdown-line"><span>' + sqft.toLocaleString() + ' sqft × $' + (p.perSqft / 100).toFixed(2) + ' = ' + formatCents(sqftTotal) + '</span><span></span></span>');
            lines.push('<span class="breakdown-line"><span>Minimum price applied</span><span>' + formatCents(p.min) + '</span></span>');
        }

        if (rush) {
            total += RUSH_FEE;
            lines.push('<span class="breakdown-line rush-line"><span>Rush service</span><span>+' + formatCents(RUSH_FEE) + '</span></span>');
        }

        priceEl.textContent = formatCents(total);
        if (breakdownEl) {
            breakdownEl.innerHTML = lines.join('') + '<span class="breakdown-total"><span>Total</span><span>' + formatCents(total) + '</span></span>';
        }
        if (noteEl) noteEl.style.display = 'none';
    }

    function formatCents(cents) {
        return '$' + (cents / 100).toFixed(2).replace(/\.00$/, '');
    }

    // --- Conditional fields ---
    function updateConditionalFields() {
        var service = getSelectedService();

        // Show/hide building detail sections based on service
        var loadCalcFields = $('#loadCalcFields');
        var rescheckFields = $('#rescheckFields');

        var showLoad = ['manual_j', 'manual_jd', 'manual_jds'].indexOf(service) !== -1;
        var showRescheck = service === 'rescheck';

        if (loadCalcFields) loadCalcFields.style.display = showLoad ? '' : 'none';
        if (rescheckFields) rescheckFields.style.display = showRescheck ? '' : 'none';

        // Year built only for existing homes
        var yearBuiltGroup = $('#yearBuiltGroup');
        var projectType = $('[name="project_type"]');
        if (yearBuiltGroup && projectType) {
            yearBuiltGroup.style.display = projectType.value === 'existing' ? '' : 'none';
        }
    }

    // --- File upload ---
    function initFileUpload() {
        var dropzone = $('#fileDropzone');
        var input = $('#fileInput');
        var fileList = $('#fileList');

        if (!dropzone || !input) return;

        // Click to browse
        dropzone.addEventListener('click', function () {
            input.click();
        });

        input.addEventListener('change', function () {
            handleFiles(input.files);
            input.value = '';
        });

        // Drag and drop
        dropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });

        dropzone.addEventListener('dragleave', function () {
            dropzone.classList.remove('dragover');
        });

        dropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });
    }

    function handleFiles(files) {
        var maxFiles = 5;
        var maxSize = 10 * 1024 * 1024; // 10MB
        var allowedExts = ['pdf', 'dwg', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

        for (var i = 0; i < files.length; i++) {
            if (uploadedFiles.length >= maxFiles) {
                alert('Maximum ' + maxFiles + ' files allowed.');
                break;
            }

            var file = files[i];
            var ext = file.name.split('.').pop().toLowerCase();

            if (allowedExts.indexOf(ext) === -1) {
                alert(file.name + ': File type not allowed. Please upload PDF, DWG, JPG, or PNG files.');
                continue;
            }

            if (file.size > maxSize) {
                alert(file.name + ': File is too large. Maximum 10MB per file.');
                continue;
            }

            uploadedFiles.push(file);
        }

        renderFileList();
    }

    function renderFileList() {
        var list = $('#fileList');
        if (!list) return;

        if (uploadedFiles.length === 0) {
            list.innerHTML = '';
            return;
        }

        var html = '';
        uploadedFiles.forEach(function (file, idx) {
            var size = file.size < 1048576
                ? (file.size / 1024).toFixed(0) + ' KB'
                : (file.size / 1048576).toFixed(1) + ' MB';
            html += '<div class="file-item"><span class="file-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span><span class="file-name">' + escHtml(file.name) + '</span><span class="file-size">' + size + '</span><button type="button" class="file-remove" data-idx="' + idx + '">&times;</button></div>';
        });
        list.innerHTML = html;

        // Bind remove buttons
        $$('.file-remove', list).forEach(function (btn) {
            btn.addEventListener('click', function () {
                uploadedFiles.splice(parseInt(btn.dataset.idx), 1);
                renderFileList();
            });
        });
    }

    // --- Review ---
    function buildReview() {
        var reviewEl = $('#reviewSummary');
        if (!reviewEl) return;

        var service = getSelectedService();
        var p = PRICING[service];
        var sqft = getSqft();
        var rush = isRush();

        var rows = [];
        rows.push(reviewRow('Service', p ? p.label : service));
        if (rush) rows.push(reviewRow('Rush Service', 'Yes (+$75)'));

        // Client info
        var clientFields = [
            { name: 'customer_name', label: 'Client Name' },
            { name: 'customer_email', label: 'Email' },
            { name: 'customer_phone', label: 'Phone' },
            { name: 'customer_company', label: 'Company' }
        ];
        clientFields.forEach(function (f) {
            var el = $('[name="' + f.name + '"]');
            if (el && el.value.trim()) {
                rows.push(reviewRow(f.label, el.value.trim()));
            }
        });

        // Project info
        var fields = [
            { name: 'project_type', label: 'Project Type' },
            { name: 'address_street', label: 'Address' },
            { name: 'address_city', label: 'City' },
            { name: 'address_state', label: 'State' },
            { name: 'address_zip', label: 'ZIP' },
            { name: 'sqft', label: 'Square Footage' },
            { name: 'front_door_faces', label: 'Front Door Faces' },
            { name: 'floor_material', label: 'Floor Material' },
            { name: 'roof_ceiling_material', label: 'Roof/Ceiling' },
            { name: 'roofing_type', label: 'Roofing Type' },
            { name: 'roof_insulation', label: 'Roof Insulation' },
            { name: 'wall_material', label: 'Wall Material' },
            { name: 'wall_thickness', label: 'Wall Thickness' },
            { name: 'wall_insulation_type', label: 'Wall Insulation Type' },
            { name: 'wall_insulation', label: 'Wall Insulation R-Value' },
            { name: 'siding_type', label: 'Siding Type' },
            { name: 'glass_u_value', label: 'Glass U-Value' },
            { name: 'glass_shgc', label: 'Glass SHGC' },
            { name: 'exterior_door', label: 'Exterior Door' }
        ];
        fields.forEach(function (f) {
            var el = $('[name="' + f.name + '"]');
            if (el && el.value.trim()) {
                var val = el.value.trim();
                // For select elements, show the selected option text
                if (el.tagName === 'SELECT' && el.selectedIndex > 0) {
                    val = el.options[el.selectedIndex].text;
                }
                rows.push(reviewRow(f.label, val));
            }
        });

        // Files
        if (uploadedFiles.length > 0) {
            rows.push(reviewRow('Floor Plans', uploadedFiles.length + ' file' + (uploadedFiles.length > 1 ? 's' : '')));
        } else {
            var emailLater = $('[name="email_plans_later"]');
            if (emailLater && emailLater.checked) {
                rows.push(reviewRow('Floor Plans', "I'll email plans later"));
            }
        }

        // Notes
        var notes = $('[name="notes"]');
        if (notes && notes.value.trim()) {
            rows.push(reviewRow('Notes', notes.value.trim().substring(0, 200) + (notes.value.trim().length > 200 ? '...' : '')));
        }

        // Price
        if (p && service !== 'commercial') {
            var total;
            if (service === 'rescheck') {
                total = p.min;
            } else {
                var sqftTotal = p.perSqft * sqft;
                total = Math.max(sqftTotal, p.min);
            }
            if (rush) total += RUSH_FEE;
            rows.push('<div class="review-total"><span>Estimated Total</span><span>' + formatCents(total) + '</span></div>');
        } else if (service === 'commercial') {
            rows.push('<div class="review-total"><span>Pricing</span><span>Call for Quote</span></div>');
        }

        reviewEl.innerHTML = rows.join('');
    }

    function reviewRow(label, value) {
        return '<div class="review-row"><span class="review-label">' + escHtml(label) + '</span><span class="review-value">' + escHtml(String(value)) + '</span></div>';
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // --- Form submission ---
    function submitOrder() {
        if (!validateStep(totalSteps)) return;

        var submitBtn = $('#wizardSubmit');
        var statusEl = $('#wizardStatus');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
        if (statusEl) { statusEl.className = 'wizard-status'; statusEl.textContent = ''; }

        var formData = new FormData();

        // Service details
        formData.append('service_type', getSelectedService());
        formData.append('sqft', getSqft());
        formData.append('rush', isRush() ? '1' : '0');

        // All form fields
        var allFields = $$('.wizard input, .wizard select, .wizard textarea');
        allFields.forEach(function (el) {
            if (!el.name || el.name === 'service_type' || el.name === 'sqft' || el.name === 'rush' || el.name === 'terms' || el.type === 'file') return;
            if (el.type === 'radio' && !el.checked) return;
            if (el.type === 'checkbox') {
                formData.append(el.name, el.checked ? '1' : '0');
                return;
            }
            formData.append(el.name, el.value);
        });

        // Files
        uploadedFiles.forEach(function (file) {
            formData.append('floor_plans[]', file);
        });

        fetch('/api/submit-order.php', {
            method: 'POST',
            body: formData
        })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
            if (result.ok && result.data.success) {
                // Show success
                var wizard = $('.wizard');
                if (wizard) {
                    wizard.innerHTML = '<div class="wizard-success"><div class="success-icon"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><h2>Order Submitted!</h2><p class="success-order-num">Order #' + escHtml(result.data.order_number) + '</p><p>Thank you for your order. Thomas will review your project details and get started right away.</p><p>A confirmation email has been sent to <strong>' + escHtml(result.data.email) + '</strong> with your order details and portal login information.</p><div class="success-actions"><a href="/portal/dashboard.php" class="btn btn-primary">View Your Order</a><a href="/professionals.html" class="btn btn-outline-dark">Back to Services</a></div></div>';
                }

                // Track conversion
                if (typeof fbq === 'function') {
                    fbq('track', 'Purchase', {
                        content_name: result.data.service_type,
                        value: result.data.total / 100,
                        currency: 'USD'
                    });
                }
            } else {
                if (statusEl) {
                    statusEl.className = 'wizard-status error';
                    statusEl.textContent = result.data.message || 'Something went wrong. Please call (479) 207-2454.';
                }
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Order';
            }
        })
        .catch(function () {
            if (statusEl) {
                statusEl.className = 'wizard-status error';
                statusEl.textContent = 'Network error. Please call us at (479) 207-2454.';
            }
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Order';
        });
    }

    // --- Init ---
    function init() {
        // Step navigation buttons
        var prevBtn = $('#wizardPrev');
        var nextBtn = $('#wizardNext');
        var submitBtn = $('#wizardSubmit');

        if (prevBtn) prevBtn.addEventListener('click', function () { goToStep(currentStep - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { goToStep(currentStep + 1); });
        if (submitBtn) submitBtn.addEventListener('click', function (e) { e.preventDefault(); submitOrder(); });

        // Progress step clicks
        $$('.progress-step').forEach(function (dot) {
            dot.addEventListener('click', function () {
                var targetStep = parseInt(dot.dataset.step);
                if (targetStep < currentStep) goToStep(targetStep);
            });
        });

        // Service type radio changes
        $$('input[name="service_type"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                // Highlight selected card
                $$('.service-option').forEach(function (card) { card.classList.remove('selected'); });
                var parent = radio.closest('.service-option');
                if (parent) parent.classList.add('selected');
                updatePrice();
                updateConditionalFields();
            });
        });

        // Rush toggle
        var rushToggle = $('[name="rush"]');
        if (rushToggle) rushToggle.addEventListener('change', updatePrice);

        // Sqft field triggers price recalculation
        var sqftField = $('[name="sqft"]');
        if (sqftField) sqftField.addEventListener('input', updatePrice);

        // Project type changes conditional fields
        var projectType = $('[name="project_type"]');
        if (projectType) projectType.addEventListener('change', updateConditionalFields);

        // File upload
        initFileUpload();

        // Check for pre-selected service from URL param
        var params = new URLSearchParams(window.location.search);
        var preselect = params.get('service');
        if (preselect) {
            var radio = $('input[name="service_type"][value="' + preselect + '"]');
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change'));
            }
        }

        // Initialize state
        updatePrice();
        updateConditionalFields();
        goToStep(1);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
