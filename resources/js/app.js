import { HSCollapse } from 'preline/non-auto';
import {
    ArrowRight,
    Building2,
    Check,
    Eye,
    EyeOff,
    Heart,
    Home,
    KeyRound,
    LogIn,
    Mail,
    MapPin,
    MessageCircle,
    Menu,
    Phone,
    Search,
    ShieldCheck,
    Sparkles,
    UserRound,
    UserPlus,
    X,
    createIcons,
} from 'lucide';

document.addEventListener('DOMContentLoaded', () => {
    HSCollapse.autoInit();

    createIcons({
        icons: {
            ArrowRight,
            Building2,
            Check,
            Eye,
            EyeOff,
            Heart,
            Home,
            KeyRound,
            LogIn,
            Mail,
            MapPin,
            MessageCircle,
            Menu,
            Phone,
            Search,
            ShieldCheck,
            Sparkles,
            UserRound,
            UserPlus,
            X,
        },
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
});

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
