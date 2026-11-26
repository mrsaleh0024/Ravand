import type { Directive } from 'vue';
import type { Auth } from '@/types/auth';
import type { Membership, TenantInfo, TenantRole } from '@/types/tenancy';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth & {
                role?: TenantRole | null;
                memberships?: Membership[];
            };
            tenant?: TenantInfo | null;
            sidebarOpen: boolean;
            /** One-time flashed values (toasts, temp password, Bale test result). */
            flash?: Record<string, unknown>;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
