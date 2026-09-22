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
    MapPin,
    Menu,
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
            MapPin,
            Menu,
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

    document.querySelector('[data-error-summary]')?.focus({ preventScroll: true });

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
