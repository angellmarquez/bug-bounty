export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    two_factor_enabled?: boolean;
    email_verified_at: string | null;
    reputation_score: number;
    roles: string[];
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
