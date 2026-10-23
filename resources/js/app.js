import { HSCollapse } from 'preline/non-auto';
import {
    AlignLeft,
    AlignCenter,
    AlignRight,
    AlignJustify,
    ArrowRight,
    Building2,
    CalendarCheck2,
    CalendarClock,
    CalendarDays,
    CalendarPlus2,
    CalendarX2,
    ChevronDown,
    ChevronUp,
    Check,
    CheckCheck,
    CircleAlert,
    CircleX,
    Clock3,
    DoorOpen,
    Eye,
    EyeOff,
    Flag,
    Heart,
    Home,
    Indent,
    KeyRound,
    LogIn,
    Image,
    List,
    ListFilter,
    ListOrdered,
    LayoutDashboard,
    Mail,
    MapPin,
    MapPinned,
    MessageCircle,
    Menu,
    Outdent,
    Phone,
    Pencil,
    Plus,
    Redo,
    Search,
    ShieldCheck,
    ShieldAlert,
    Sparkles,
    SlidersHorizontal,
    Trash2,
    Type,
    Undo,
    UserRound,
    UserPlus,
    Users,
    X,
    createIcons,
} from 'lucide';

const lucideIcons = {
    AlignLeft,
    AlignCenter,
    AlignRight,
    AlignJustify,
    ArrowRight,
    Building2,
    CalendarCheck2,
    CalendarClock,
    CalendarDays,
    CalendarPlus2,
    CalendarX2,
    ChevronDown,
    ChevronUp,
    Check,
    CheckCheck,
    CircleAlert,
    CircleX,
    Clock3,
    DoorOpen,
    Eye,
    EyeOff,
    Flag,
    Heart,
    Home,
    Indent,
    KeyRound,
    LogIn,
    Image,
    List,
    ListFilter,
    ListOrdered,
    LayoutDashboard,
    Mail,
    MapPin,
    MapPinned,
    MessageCircle,
    Menu,
    Outdent,
    Phone,
    Pencil,
    Plus,
    Redo,
    Search,
    ShieldCheck,
    ShieldAlert,
    Sparkles,
    SlidersHorizontal,
    Trash2,
    Type,
    Undo,
    UserRound,
    UserPlus,
    Users,
    X,
};

document.addEventListener('DOMContentLoaded', () => {
    HSCollapse.autoInit();

    createIcons({
        icons: lucideIcons,
        attrs: {
            'aria-hidden': 'true',
            'stroke-width': 2,
        },
    });

    const errorSummary = document.querySelector('[data-error-summary]');
    if (errorSummary) {
        errorSummary.focus();
        errorSummary.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    document.querySelectorAll('[data-avatar-image]').forEach((image) => {
        const fallback = document.getElementById(image.dataset.avatarFallback);

        const showFallback = () => {
            image.classList.add('hidden');
            fallback?.classList.remove('hidden');
        };

        image.addEventListener('error', showFallback, { once: true });

        if (image.complete && image.naturalWidth === 0) {
            showFallback();
        }
    });

    document.querySelectorAll('[data-submit-once]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();

                return;
            }

            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');

            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
                button.disabled = true;

                const label = button.querySelector('[data-submit-label]');

                if (label && button.dataset.defaultLabel === undefined) {
                    button.dataset.defaultLabel = label.textContent;
                }

                if (label && button.dataset.loadingText) {
                    label.textContent = button.dataset.loadingText;
                }
            });
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.dataset.passwordToggle);

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        button.addEventListener('click', () => {
            const isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';
            button.setAttribute('aria-pressed', String(!isVisible));
            button.setAttribute(
                'aria-label',
                isVisible ? button.dataset.passwordShowLabel : button.dataset.passwordHideLabel,
            );
            button.querySelector('[data-password-show]')?.classList.toggle('hidden', !isVisible);
            button.querySelector('[data-password-hide]')?.classList.toggle('hidden', isVisible);
            input.focus({ preventScroll: true });
        });
    });

    document.querySelectorAll('[data-filter-toggle]').forEach((button) => {
        const form = button.closest('form');
        const panel = form?.querySelector('[data-filter-panel]');
        const chevron = button.querySelector('[data-filter-chevron]');

        button.addEventListener('click', () => {
            if (!panel) {
                return;
            }
            const isHidden = panel.classList.contains('hidden');
            panel.classList.toggle('hidden', !isHidden);
            button.setAttribute('aria-expanded', String(isHidden));
            if (chevron) {
                chevron.classList.toggle('rotate-180', isHidden);
            }
        });

        form?.querySelectorAll('[data-filter-close]').forEach((closeBtn) => {
            closeBtn.addEventListener('click', () => {
                if (!panel) {
                    return;
                }
                panel.classList.add('hidden');
                button.setAttribute('aria-expanded', 'false');
                if (chevron) {
                    chevron.classList.remove('rotate-180');
                }
            });
        });
    });

    document.querySelectorAll('[data-listing-form], [data-public-location-filters]').forEach(initializeLocationFilters);

    document.querySelectorAll('[data-listing-form]').forEach((form) => {
        initializeListingForm(form);
    });

    initializeCurrencyInputs();
    initializeRichEditors();
});

function initializeLocationFilters(form) {
    const province = form.querySelector('[data-location="province"]');
    const district = form.querySelector('[data-location="district"]');
    const ward = form.querySelector('[data-location="ward"]');

    const syncLocationOptions = (select, parentValue) => {
        if (!select) {
            return;
        }

        let hasSelectedOption = false;

        select.querySelectorAll('option[data-location-parent]').forEach((option) => {
            const visible = parentValue !== '' && option.dataset.locationParent === parentValue;
            option.hidden = !visible;
            option.disabled = !visible;

            if (!visible && option.selected) {
                option.selected = false;
            }

            if (visible && option.selected) {
                hasSelectedOption = true;
            }
        });

        select.disabled = parentValue === '';

        if (!hasSelectedOption) {
            select.value = '';
        }
    };

    const syncLocations = () => {
        syncLocationOptions(district, province?.value ?? '');
        syncLocationOptions(ward, district?.value ?? '');
    };

    province?.addEventListener('change', () => {
        if (district) {
            district.value = '';
        }
        if (ward) {
            ward.value = '';
        }
        syncLocations();
    });

    district?.addEventListener('change', () => {
        if (ward) {
            ward.value = '';
        }
        syncLocations();
    });

    syncLocations();
}

function initializeListingForm(form) {
    const imageInput = form.querySelector('[data-image-input]');
    const imageGrid = form.querySelector('[data-image-grid]');
    const imageCount = form.querySelector('[data-image-count]');
    const newFiles = new Map();
    let nextFileKey = 0;

    const selectFirstCover = () => {
        const firstRadio = imageGrid?.querySelector('input[type="radio"][data-cover-radio]');

        if (firstRadio instanceof HTMLInputElement) {
            firstRadio.checked = true;
        }
    };

    const syncImageState = () => {
        if (!imageGrid) {
            return;
        }

        let newIndex = 0;
        const orderedFiles = [];

        imageGrid.querySelectorAll('[data-image-item]').forEach((item, index) => {
            item.querySelector('[data-image-order]')?.replaceChildren(document.createTextNode(String(index + 1)));
            const radio = item.querySelector('[data-cover-radio]');

            if (item.dataset.imageKind === 'new') {
                const key = item.dataset.newKey;
                const file = newFiles.get(key);

                if (file) {
                    orderedFiles.push(file);
                }

                if (radio instanceof HTMLInputElement) {
                    radio.value = `new:${newIndex}`;
                }

                newIndex += 1;
            }
        });

        if (imageInput instanceof HTMLInputElement && typeof DataTransfer !== 'undefined') {
            const transfer = new DataTransfer();
            orderedFiles.forEach((file) => transfer.items.add(file));
            imageInput.files = transfer.files;
        }

        if (imageCount) {
            imageCount.textContent = `${imageGrid.querySelectorAll('[data-image-item]').length}/8`;
        }
    };

    const addImagePreview = (file) => {
        if (!imageGrid || !file.type.startsWith('image/')) {
            return;
        }

        const key = String(nextFileKey++);
        newFiles.set(key, file);
        const item = document.createElement('article');
        item.className = 'listing-image-item group relative overflow-hidden rounded-control border border-line bg-slate-50';
        item.dataset.imageItem = '';
        item.dataset.imageKind = 'new';
        item.dataset.newKey = key;
        item.innerHTML = `
            <img class="aspect-[4/3] w-full object-cover" alt="" data-preview-image>
            <div class="flex items-center justify-between gap-2 border-t border-line bg-white p-2">
                <label class="inline-flex min-h-10 items-center gap-2 text-xs font-bold text-slate-700">
                    <input class="size-4 accent-brand-600" type="radio" name="cover_selection" value="new:0" data-cover-radio>
                    <span>${form.dataset.coverLabel}</span>
                </label>
                <button class="grid size-10 place-items-center rounded-control text-slate-500 transition hover:bg-danger-50 hover:text-danger-700" type="button" aria-label="${form.dataset.removeLabel}" data-image-remove>
                    <i class="size-4" data-lucide="trash-2"></i>
                </button>
            </div>
            <div class="flex items-center justify-between border-t border-line px-2 py-1.5 text-xs text-slate-500">
                <span><span data-image-order></span> · <span data-image-file-name></span></span>
                <span class="flex gap-1">
                    <button class="grid size-8 place-items-center rounded-control hover:bg-slate-100" type="button" aria-label="${form.dataset.moveUpLabel}" data-image-move="up"><i class="size-4" data-lucide="chevron-up"></i></button>
                    <button class="grid size-8 place-items-center rounded-control hover:bg-slate-100" type="button" aria-label="${form.dataset.moveDownLabel}" data-image-move="down"><i class="size-4" data-lucide="chevron-down"></i></button>
                </span>
            </div>`;
        const image = item.querySelector('[data-preview-image]');
        item.querySelector('[data-image-file-name]').textContent = file.name;
        image.src = URL.createObjectURL(file);
        image.addEventListener('load', () => URL.revokeObjectURL(image.src), { once: true });
        imageGrid.append(item);
        createIcons({ icons: lucideIcons, attrs: { 'aria-hidden': 'true', 'stroke-width': 2 } });
        syncImageState();

        if (!imageGrid.querySelector('input[type="radio"][data-cover-radio]:checked')) {
            selectFirstCover();
        }
    };

    imageInput?.addEventListener('change', () => {
        Array.from(imageInput.files ?? []).forEach(addImagePreview);
        syncImageState();
    });

    imageGrid?.addEventListener('click', (event) => {
        const target = event.target.closest('button');
        const item = event.target.closest('[data-image-item]');

        if (!target || !item) {
            return;
        }

        if (target.hasAttribute('data-image-remove')) {
            if (item.dataset.imageKind === 'new') {
                newFiles.delete(item.dataset.newKey);
            }
            const wasChecked = item.querySelector('[data-cover-radio]')?.checked;
            item.remove();
            if (wasChecked) {
                selectFirstCover();
            }
            syncImageState();

            return;
        }

        const direction = target.dataset.imageMove;
        const sibling = direction === 'up' ? item.previousElementSibling : item.nextElementSibling;

        if (sibling) {
            if (direction === 'up') {
                imageGrid.insertBefore(item, sibling);
            } else {
                imageGrid.insertBefore(sibling, item);
            }
            syncImageState();
        }
    });

    syncImageState();

    form.querySelectorAll('[data-fee-toggle]').forEach((toggle) => {
        const fields = toggle.closest('[data-fee-row]')?.querySelector('[data-fee-fields]');

        if (!fields) {
            return;
        }

        const syncFeeFields = () => {
            fields.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = !toggle.checked;
            });
        };

        toggle.addEventListener('change', syncFeeFields);
        syncFeeFields();
    });

    const isCreateForm = !form.querySelector('input[name="_method"]');
    if (isCreateForm) {
        const DRAFT_KEY = 'roomrental_listing_create_draft';

        if (window.location.search.includes('clear_draft=1')) {
            localStorage.removeItem(DRAFT_KEY);
        }

        const rawDraft = localStorage.getItem(DRAFT_KEY);
        if (rawDraft) {
            try {
                const draft = JSON.parse(rawDraft);
                Object.entries(draft).forEach(([key, val]) => {
                    const el = form.elements.namedItem(key);
                    if (!el) return;

                    if (el instanceof HTMLInputElement && (el.type === 'text' || el.type === 'number')) {
                        if (!el.value && val !== null && val !== undefined) el.value = val;
                    } else if (el instanceof HTMLTextAreaElement) {
                        if (!el.value && val) el.value = val;
                    } else if (el instanceof HTMLSelectElement) {
                        if (!el.value && val) {
                            el.value = val;
                            el.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    } else if (el instanceof RadioNodeList && val) {
                        if (!el.value) el.value = val;
                    }
                });
            } catch (_) {}
        }

        const saveDraft = () => {
            const data = {};
            Array.from(form.elements).forEach((el) => {
                if (!el.name || el.type === 'file' || el.type === 'password' || el.name === '_token') return;
                if (el.type === 'checkbox') {
                    if (el.checked) {
                        data[el.name] = data[el.name] || [];
                        data[el.name].push(el.value);
                    }
                } else if (el.type === 'radio') {
                    if (el.checked) data[el.name] = el.value;
                } else {
                    data[el.name] = el.value;
                }
            });
            localStorage.setItem(DRAFT_KEY, JSON.stringify(data));
        };

        form.addEventListener('input', saveDraft);
        form.addEventListener('change', saveDraft);

        document.querySelectorAll('[data-clear-draft-link]').forEach((btn) => {
            btn.addEventListener('click', () => {
                localStorage.removeItem(DRAFT_KEY);
            });
        });
    }
}

if (document.querySelector('[role="status"].border-success-100')) {
    localStorage.removeItem('roomrental_listing_create_draft');
}

window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-submit-once]').forEach((form) => {
        form.dataset.submitting = 'false';
        form.removeAttribute('aria-busy');

        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
            button.disabled = false;

            const label = button.querySelector('[data-submit-label]');

            if (label && button.dataset.defaultLabel !== undefined) {
                label.textContent = button.dataset.defaultLabel;
            }
        });
    });
});

function initializeCurrencyInputs() {
    const formatVnd = (value) => {
        const num = Math.round(Number(value));
        if (isNaN(num) || num <= 0) {
            return '';
        }

        const formatted = new Intl.NumberFormat('vi-VN').format(num) + ' ₫';
        let words = '';
        if (num >= 1000000000) {
            words = ` (~ ${(num / 1000000000).toFixed(1).replace('.0', '')} tỷ)`;
        } else if (num >= 1000000) {
            words = ` (~ ${(num / 1000000).toFixed(1).replace('.0', '')} triệu)`;
        } else if (num >= 1000) {
            words = ` (~ ${(num / 1000).toFixed(0)} nghìn)`;
        }

        return `≈ ${formatted}${words}`;
    };

    document.querySelectorAll('[data-currency-input]').forEach((input) => {
        const container = input.closest('.relative');
        if (!container) return;

        let preview = container.parentElement?.querySelector('[data-currency-preview]');
        if (!preview) {
            preview = document.createElement('p');
            preview.className = 'mt-1 text-xs font-semibold text-brand-700 hidden';
            preview.dataset.currencyPreview = '';
            container.after(preview);
        }

        const updatePreview = () => {
            const val = parseFloat(input.value);
            if (!isNaN(val) && val >= 1000) {
                preview.textContent = formatVnd(val);
                preview.classList.remove('hidden');
            } else {
                preview.classList.add('hidden');
            }
        };

        input.addEventListener('input', updatePreview);
        if (input.value) {
            updatePreview();
        }
    });
}

function initializeRichEditors() {
    document.querySelectorAll('[data-rich-editor]').forEach((wrapper) => {
        const editor = wrapper.querySelector('[data-editor-content]');
        const sourceTextarea = wrapper.querySelector('[data-editor-source]');
        const hiddenInput = wrapper.querySelector('[data-editor-hidden]');
        const wordCountEl = wrapper.querySelector('[data-word-count]');
        const charCountEl = wrapper.querySelector('[data-char-count]');
        const counterBadge = wrapper.querySelector('[data-counter-badge]');
        const minWords = parseInt(wrapper.dataset.minWords || '0', 10);
        const maxWords = parseInt(wrapper.dataset.maxWords || '0', 10);
        const isSingleLine = wrapper.dataset.singleLine === 'true';

        if (!editor || !hiddenInput) {
            return;
        }

        if (hiddenInput.value && !editor.innerText.trim()) {
            editor.innerHTML = hiddenInput.value;
        }

        hiddenInput.addEventListener('focus', () => {
            if (isSourceMode && sourceTextarea) {
                sourceTextarea.focus();
            } else {
                editor.focus();
            }
        });

        let isSourceMode = false;

        const updateCounts = () => {
            const text = (isSourceMode ? sourceTextarea.value : editor.innerText).trim();
            const words = text ? text.split(/\s+/).filter(Boolean).length : 0;
            const chars = text.length;

            if (wordCountEl) {
                wordCountEl.textContent = words;
            }
            if (charCountEl) {
                charCountEl.textContent = chars;
            }

            if (counterBadge) {
                if (minWords > 0) {
                    if (words < minWords) {
                        counterBadge.className = 'text-xs font-semibold text-warning-700 transition-colors';
                        counterBadge.innerHTML = `<span class="font-bold">${words}</span> / ${minWords} từ (Còn ngắn, cần tối thiểu ${minWords} từ)`;
                    } else if (maxWords > 0 && words > maxWords) {
                        counterBadge.className = 'text-xs font-semibold text-danger-700 transition-colors';
                        counterBadge.innerHTML = `<span class="font-bold">${words}</span> / ${maxWords} từ (Vượt quá giới hạn)`;
                    } else {
                        counterBadge.className = 'text-xs font-semibold text-success-700 transition-colors';
                        counterBadge.innerHTML = `<span class="font-bold">${words}</span> từ (Đạt yêu cầu) · ${chars} ký tự`;
                    }
                } else {
                    counterBadge.className = 'text-xs font-semibold text-slate-500 transition-colors';
                    counterBadge.innerHTML = `<span>${words}</span> từ (<span>${chars}</span> ký tự)`;
                }
            }
        };

        const syncToHidden = () => {
            if (isSourceMode) {
                hiddenInput.value = sourceTextarea.value.trim();
            } else {
                const text = editor.innerText.trim();
                hiddenInput.value = text === '' ? '' : editor.innerHTML;
            }
            updateCounts();
        };

        if (isSingleLine) {
            editor.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });
        }

        wrapper.querySelectorAll('[data-toolbar-btn]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const cmd = btn.dataset.toolbarBtn;
                if (isSourceMode) {
                    return;
                }
                editor.focus();
                document.execCommand(cmd, false, null);
                syncToHidden();
            });
        });

        wrapper.querySelectorAll('[data-toolbar-cmd]').forEach((select) => {
            select.addEventListener('change', (e) => {
                const cmd = select.dataset.toolbarCmd;
                let val = select.value;
                if (isSourceMode) {
                    return;
                }
                editor.focus();
                if (cmd === 'formatBlock') {
                    val = `<${val}>`;
                }
                document.execCommand(cmd, false, val);
                syncToHidden();
            });
        });

        wrapper.querySelectorAll('[data-toolbar-color]').forEach((colorInput) => {
            colorInput.addEventListener('input', () => {
                const cmd = colorInput.dataset.toolbarColor;
                if (isSourceMode) {
                    return;
                }
                editor.focus();
                document.execCommand(cmd, false, colorInput.value);
                const indicator = colorInput.parentElement?.querySelector('[data-color-indicator]');
                if (indicator) {
                    indicator.style.backgroundColor = colorInput.value;
                }
                syncToHidden();
            });
        });

        const sourceBtn = wrapper.querySelector('[data-toolbar-action="source"]');
        if (sourceBtn && sourceTextarea) {
            sourceBtn.addEventListener('click', (e) => {
                e.preventDefault();
                isSourceMode = !isSourceMode;
                if (isSourceMode) {
                    sourceTextarea.value = editor.innerHTML;
                    editor.classList.add('hidden');
                    sourceTextarea.classList.remove('hidden');
                    sourceBtn.classList.add('bg-slate-200', 'border-slate-400');
                    sourceTextarea.focus();
                } else {
                    editor.innerHTML = sourceTextarea.value;
                    sourceTextarea.classList.add('hidden');
                    editor.classList.remove('hidden');
                    sourceBtn.classList.remove('bg-slate-200', 'border-slate-400');
                    editor.focus();
                }
                syncToHidden();
            });

            sourceTextarea.addEventListener('input', syncToHidden);
        }

        editor.addEventListener('input', syncToHidden);
        editor.addEventListener('blur', syncToHidden);
        editor.addEventListener('keyup', syncToHidden);
        editor.addEventListener('paste', () => {
            setTimeout(syncToHidden, 10);
        });

        const form = wrapper.closest('form');
        if (form) {
            form.addEventListener('submit', syncToHidden);
        }

        updateCounts();
    });
}

