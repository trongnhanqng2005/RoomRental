import { HSCollapse } from 'preline/non-auto';
import {
    ArrowRight,
    Building2,
    ChevronDown,
    ChevronUp,
    Check,
    CircleAlert,
    Clock3,
    DoorOpen,
    Eye,
    EyeOff,
    Heart,
    Home,
    KeyRound,
    LogIn,
    Image,
    List,
    ListFilter,
    Mail,
    MapPin,
    MapPinned,
    MessageCircle,
    Menu,
    Phone,
    Pencil,
    Plus,
    Search,
    ShieldCheck,
    ShieldAlert,
    Sparkles,
    Trash2,
    Type,
    UserRound,
    UserPlus,
    X,
    createIcons,
} from 'lucide';

const lucideIcons = {
    ArrowRight,
    Building2,
    ChevronDown,
    ChevronUp,
    Check,
    CircleAlert,
    Clock3,
    DoorOpen,
    Eye,
    EyeOff,
    Heart,
    Home,
    KeyRound,
    LogIn,
    Image,
    List,
    ListFilter,
    Mail,
    MapPin,
    MapPinned,
    MessageCircle,
    Menu,
    Phone,
    Pencil,
    Plus,
    Search,
    ShieldCheck,
    ShieldAlert,
    Sparkles,
    Trash2,
    Type,
    UserRound,
    UserPlus,
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

    document.querySelector('[data-error-summary]')?.focus();

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

    document.querySelectorAll('[data-listing-form], [data-public-location-filters]').forEach(initializeLocationFilters);

    document.querySelectorAll('[data-listing-form]').forEach((form) => {
        initializeListingForm(form);
    });
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
