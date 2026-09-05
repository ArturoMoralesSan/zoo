<script setup lang="ts">
import { computed } from 'vue';
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

interface Profile {
    avatar: string | null;
}

type Props = {
    user: User;
    profile?: Profile | null;
    showEmail?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    profile: null,
    showEmail: false,
});

const { getInitials } = useInitials();

const showAvatar = computed(() => {
    return !!props.profile?.avatar;
});

const avatarUrl = computed(() => {
    if (!props.profile?.avatar) {
        return '';
    }

    return `/storage/${props.profile.avatar}`;
});
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage
            v-if="showAvatar"
            :src="avatarUrl"
            :alt="user.name"
        />

        <AvatarFallback
            class="rounded-lg text-black dark:text-white"
        >
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div
        class="grid flex-1 text-left text-sm leading-tight"
    >
        <span class="truncate font-medium">
            {{ user.name }}
        </span>

        <span
            v-if="showEmail"
            class="truncate text-xs text-muted-foreground"
        >
            {{ user.email }}
        </span>
    </div>
</template>