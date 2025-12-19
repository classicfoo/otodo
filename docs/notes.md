# Lessons Learned

- **Coordinate discard flows with auto-save timers.** When a user discards edits, clear both interval and timeout handles before navigating so background saves cannot race the discard flow.
- **Use queued request IDs to clean offline data.** Target queued request IDs when pruning offline caches and payload state, and notify the service worker for each ID so stale data is removed across storage layers.
- **Guard navigation-triggered saves.** Ensure any `beforeunload` handlers respect a discard-in-progress flag to prevent last-second saves from recreating discarded drafts.
- **Reset dirty state promptly.** Clear dirty flags as soon as the discard is confirmed to avoid routing through online save paths due to stale state.
