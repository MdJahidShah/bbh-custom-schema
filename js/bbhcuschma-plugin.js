document.addEventListener('DOMContentLoaded', function() {
    var toggle = document.getElementById('bbhcuschma-schema-toggle');
    var box = document.getElementById('bbhcuschma-schema-box');

    if (toggle && box) {
        toggle.addEventListener('click', function() {
            if (box.style.display === 'none' || box.style.display === '') {
                box.style.display = 'block';
                toggle.textContent = '▼ Custom Schema (Click to Collapse)';
            } else {
                box.style.display = 'none';
                toggle.textContent = '➤ Custom Schema (Click to Expand)';
            }
        });
    }

    var validateBtn = document.getElementById('bbhcuschma_validate_btn');
    var resultSpan = document.getElementById('bbhcuschma_validate_result');
    var fixCombineSpan = document.getElementById('bbhcuschma_fix_span');
    if (!fixCombineSpan) {
        fixCombineSpan = document.createElement('span');
        fixCombineSpan.id = 'bbhcuschma_fix_span';
        fixCombineSpan.style.display = 'none';
        if (resultSpan && resultSpan.parentNode) {
            resultSpan.parentNode.appendChild(fixCombineSpan);
        }
    }
    var textarea = document.getElementById('bbhcuschma_custom_schema');

    if (validateBtn && resultSpan && textarea) {
        validateBtn.addEventListener('click', function() {
            var jsonValue = textarea.value.trim();

            resultSpan.style.display = 'inline-block';

            if (!jsonValue) {
                resultSpan.textContent = 'Please enter JSON-LD schema to validate.';
                resultSpan.style.color = '#d63638';
                return;
            }

            try {
                JSON.parse(jsonValue);
                resultSpan.textContent = 'Valid JSON';
                resultSpan.style.color = '#00a32a';
                if (fixCombineSpan) fixCombineSpan.style.display = 'none';

                if (typeof bbhcuschmaValidate !== 'undefined' && bbhcuschmaValidate.ajaxUrl) {
                    jQuery.ajax({
                        url: bbhcuschmaValidate.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'bbhcuschma_validate_json',
                            nonce: bbhcuschmaValidate.nonce,
                            json: jsonValue
                        },
                        success: function(response) {
                            if (!response.success) {
                                resultSpan.textContent = response.data.message;
                                resultSpan.style.color = '#d63638';
                                if (fixCombineSpan) {
                                    fixCombineSpan.innerHTML = ' <button type="button" id="bbhcuschma_fix_now" class="button button-small" style="margin-left:8px;">Combine Schema</button>';
                                    fixCombineSpan.style.display = 'inline-block';
                                    fixCombineSpan.style.fontSize = '12px';
                                    var fixNowBtn = document.getElementById('bbhcuschma_fix_now');
                                    if (fixNowBtn) {
                                        fixNowBtn.addEventListener('click', function() {
                                            jsonValue = textarea.value.trim();
                                            jQuery.ajax({
                                                url: bbhcuschmaValidate.ajaxUrl,
                                                type: 'POST',
                                                data: {
                                                    action: 'bbhcuschma_combine_schema',
                                                    nonce: bbhcuschmaValidate.nonce,
                                                    json: jsonValue
                                                },
                                                success: function(res) {
                                                    if (res.success && res.data && res.data.is_combined) {
                                                        textarea.value = res.data.combined;
                                                        resultSpan.textContent = 'Fixed! Combined ' + res.data.original_blocks + ' block(s)';
                                                        resultSpan.style.color = '#00a32a';
                                                        if (fixCombineSpan) fixCombineSpan.style.display = 'none';
                                                    } else {
                                                        resultSpan.textContent = 'Could not fix: ' + (res.data && res.data.errors ? res.data.errors.join('; ') : 'Unknown error');
                                                        resultSpan.style.color = '#d63638';
                                                    }
                                                }
                                            });
                                        });
                                    }
                                }
                            }
                        },
                        error: function() {
                            console.log('Client-side JSON validation passed, AJAX validation skipped.');
                        }
                    });
                }
            } catch (e) {
                resultSpan.textContent = 'Invalid JSON: ' + e.message;
                resultSpan.style.color = '#d63638';
                if (fixCombineSpan) {
                    fixCombineSpan.innerHTML = ' <button type="button" id="bbhcuschma_fix_now" class="button button-small" style="margin-left:8px;">Combine Schema</button>';
                    fixCombineSpan.style.display = 'inline-block';
                    fixCombineSpan.style.fontSize = '12px';
                    var fixNowBtn = document.getElementById('bbhcuschma_fix_now');
                    if (fixNowBtn) {
                        fixNowBtn.addEventListener('click', function() {
                            jsonValue = textarea.value.trim();
                            jQuery.ajax({
                                url: bbhcuschmaValidate.ajaxUrl,
                                type: 'POST',
                                data: {
                                    action: 'bbhcuschma_combine_schema',
                                    nonce: bbhcuschmaValidate.nonce,
                                    json: jsonValue
                                },
                                success: function(res) {
                                    if (res.success && res.data && res.data.is_combined) {
                                        textarea.value = res.data.combined;
                                        resultSpan.textContent = 'Fixed! Combined ' + res.data.original_blocks + ' block(s)';
                                        resultSpan.style.color = '#00a32a';
                                        if (fixCombineSpan) fixCombineSpan.style.display = 'none';
                                    } else {
                                        resultSpan.textContent = 'Could not fix: ' + (res.data && res.data.errors ? res.data.errors.join('; ') : 'Unknown error');
                                        resultSpan.style.color = '#d63638';
                                    }
                                }
                            });
                        });
                    }
                }
            }
        });
    }

    var combineBtn = document.getElementById('bbhcuschma_combine_btn');
    var combineResult = document.getElementById('bbhcuschma_combine_result');

    if (combineBtn && textarea) {
        combineBtn.addEventListener('click', function() {
            var jsonValue = textarea.value.trim();

            if (!jsonValue) {
                alert('Please enter JSON-LD schema to combine.');
                return;
            }

            combineBtn.disabled = true;
            combineBtn.textContent = 'Fixing...';

            jQuery.ajax({
                url: bbhcuschmaValidate.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bbhcuschma_combine_schema',
                    nonce: bbhcuschmaValidate.nonce,
                    json: jsonValue
                },
                success: function(response) {
                    combineBtn.disabled = false;
                    combineBtn.textContent = 'Combine Schema';

                    if (response.success && response.data) {
                        var data = response.data;
                        var hasErrors = data.errors && data.errors.length > 0;
                        var hasMessages = data.messages && data.messages.length > 0;

                        var html = '';

                        if (data.is_combined) {
                            html += '<div style="margin-bottom:12px; padding:10px; background:#eeffe5; border-left:3px solid #00a32a; border-radius:4px;">';
                            html += '<strong style="color:#00a32a;">Combined Successfully!</strong>';
                            html += '<p style="margin:5px 0 0 0;">' + data.original_blocks + ' block(s) merged into @graph structure.</p>';
                            html += '</div>';

                            var previewClass = hasErrors ? 'warning' : 'success';
                            html += '<details style="margin-top:10px;">';
                            html += '<summary style="cursor:pointer;padding:8px;background:#f5f5f5;border-radius:4px;font-weight:500;">Preview Combined Schema</summary>';
                            html += '<pre style="margin:8px 0 0 0;padding:10px;background:#1d2327;color:#fff;font-size:11px;overflow:auto;max-height:300px;border-radius:4px;">' + data.combined.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</pre>';
                            html += '</details>';

                            html += '<div style="margin-top:12px;display:flex;gap:10px;">';
                            html += '<button type="button" id="bbhcuschma_apply_combine" class="button button-primary">Apply Combined Schema</button>';
                            html += '<button type="button" id="bbhcuschma_cancel_combine" class="button">Cancel</button>';
                            html += '</div>';

                            if (hasErrors) {
                                html += '<div style="margin-top:10px;padding:8px;background:#fef7ee;border-left:3px solid #d63638;border-radius:4px;">';
                                html += '<strong style="color:#d63638;">Partial Success:</strong><ul style="margin:5px 0 0 0;padding-left:20px;color:#d63638;">';
                                for (var i = 0; i < data.errors.length; i++) {
                                    html += '<li>' + data.errors[i] + '</li>';
                                }
                                html += '</ul></div>';
                            }

                            if (hasMessages) {
                                html += '<div style="margin-top:10px;padding:8px;background:#f5f5f5;border-radius:4px;font-size:12px;">';
                                html += '<strong>Processed:</strong><ul style="margin:5px 0 0 0;padding-left:20px;">';
                                for (var j = 0; j < data.messages.length; j++) {
                                    html += '<li>' + data.messages[j] + '</li>';
                                }
                                html += '</ul></div>';
                            }

                            combineResult.innerHTML = html;

                            var applyBtn = document.getElementById('bbhcuschma_apply_combine');
                            var cancelBtn = document.getElementById('bbhcuschma_cancel_combine');

                            if (applyBtn) {
                                applyBtn.addEventListener('click', function() {
                                    textarea.value = data.combined;
                                    combineResult.style.display = 'none';
                                    alert('Combined schema applied! Use "Validity Check" to see the validity status.');
                                });
                            }

                            if (cancelBtn) {
                                cancelBtn.addEventListener('click', function() {
                                    combineResult.style.display = 'none';
                                });
                            }
                        } else {
                            html += '<div style="padding:10px;background:#fef7ee;border-left:3px solid #d63638;border-radius:4px;">';
                            html += '<strong style="color:#d63638;">Failed to combine:</strong>';
                            html += '<ul style="margin:5px 0 0 0;padding-left:20px;color:#d63638;">';
                            for (var k = 0; k < data.errors.length; k++) {
                                html += '<li>' + data.errors[k] + '</li>';
                            }
                            html += '</ul></div>';
                            combineResult.innerHTML = html;
                        }

                        combineResult.style.display = 'block';
                    } else {
                        alert('Error: ' + (response.data ? response.data.message : 'Unknown error'));
                    }
                },
                error: function() {
                    combineBtn.disabled = false;
                    combineBtn.textContent = 'Combine Schema';
                    alert('Failed to combine schema. Please try again.');
                }
            });
        });
    }
});