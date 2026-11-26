import type { Directive } from 'vue';
import type { Auth, BaleTestResult, TempPasswordFlash } from '@/types/auth';
import type { Membership, TenantInfo, TenantRole } from '@/types/tenancy';
import type { FlashToast } from '@/types/ui';

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
            [key: string]: unknown;
        };
        /** One-shot session flash payloads (Inertia::flash on the server). */
        flashDataType: {
            toast?: FlashToast;
            baleTest?: BaleTestResult;
            tempPassword?: TempPasswordFlash;
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
