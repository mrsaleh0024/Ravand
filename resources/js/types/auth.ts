export type User = {
    id: string;
    name: string;
    phone: string;
    avatar?: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type BaleConfig = {
    isOwner: boolean;
    chatId: string | null;
    codeBot: boolean;
    adminBot: boolean;
    enabled: boolean;
};

/** One-time temporary password flash shown after issuing a temp password. */
export type TempPasswordFlash = {
    user: string;
    phone: string;
    password: string;
};

export type BaleTestResult = Record<string, boolean>;
