export type ExperimentStatus = 'pending' | 'running' | 'completed' | 'failed';

export type Experiment = {
    id: number;
    name: string;
    status: ExperimentStatus;
    provider: string;
    model: string;
    max_turns: number;
    current_turn: number;
    queued_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    failed_at: string | null;
    failure_code: string | null;
    failure_message: string | null;
    messages_count?: number;
    vocabulary_entries_count?: number;
    created_at: string;
    updated_at: string;
};

export type MessageVocabulary = {
    symbol: string;
    version: number;
    meaning: string;
};

export type ExperimentMessage = {
    id: number;
    turn: number;
    role: 'sender' | 'receiver';
    symbolic_content: string;
    decoded_content: string;
    vocabulary: MessageVocabulary[];
    created_at: string;
};

export type VocabularyRevision = {
    id: number;
    version: number;
    meaning: string;
    description: string | null;
    proposer_role: 'sender' | 'receiver';
    turn: number;
    created_at?: string;
};

export type VocabularyEntry = {
    id: number;
    symbol: string;
    current: VocabularyRevision | null;
    revisions: VocabularyRevision[];
};

export type VocabularyEvent = {
    id: number;
    type: 'created' | 'revised';
    symbol: string;
    version: number;
    meaning: string;
    description: string | null;
    proposer_role: 'sender' | 'receiver';
    turn: number;
    message_id: number | null;
    occurred_at: string;
};

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
};
