export type UserRole = 'admin' | 'morador' | 'porteiro';

export type UnitSummary = {
    id: number;
    block: string | null;
    number: string;
    type: string;
    complement: string | null;
};

export type User = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    avatar?: string;
    email_verified_at: string | null;
};

export type Auth = {
    user: User;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
