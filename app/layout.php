<?php
declare(strict_types=1);

function wf_current_path(): string
{
    return (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
}

function wf_is_admin_context(): bool
{
    $path = wf_current_path();
    if (str_starts_with($path, '/admin/') || str_starts_with($path, '/setup/') || str_starts_with($path, '/auth/')) {
        return true;
    }

    // Admins viewing a player profile from the admin profiles page should keep the
    // clean admin-style layout rather than the player codex shell.
    if ($path === '/profiles/view.php' && (($_GET['admin'] ?? '') === '1')) {
        return true;
    }

    return false;
}

function wf_skill_icon_url(string $skillName): string
{
    $file = strtolower(trim($skillName));
    $file = preg_replace('/[^a-z0-9]+/', '', $file) ?? $file;
    return $file === '' ? '/assets/skills/_default.png' : '/assets/skills/' . $file . '.png';
}

function wf_sidebar_top_skills(int $profileId, int $limit = 99): array
{
    try {
        $skills = latest_skills_for_profile($profileId);
    } catch (Throwable $e) {
        return [];
    }

    usort($skills, static function (array $a, array $b): int {
        $aDisplay = rs3_display_level((string)($a['skill_name'] ?? ''), $a['level'] ?? null, $a['xp'] ?? null);
        $bDisplay = rs3_display_level((string)($b['skill_name'] ?? ''), $b['level'] ?? null, $b['xp'] ?? null);
        $levelCompare = ((int)$bDisplay['display_level']) <=> ((int)$aDisplay['display_level']);
        if ($levelCompare !== 0) return $levelCompare;
        return ((int)($b['xp'] ?? 0)) <=> ((int)($a['xp'] ?? 0));
    });

    return $limit > 0 ? array_slice($skills, 0, $limit) : $skills;
}

function wf_render_player_codex_sidebar(?array $profile): void
{
    if (!$profile) {
        echo '<aside class="player-codex-sidebar empty-codex-sidebar">';
        echo '<div class="codex-sidebar-card">';
        echo '<span class="codex-kicker">Wayfinder Codex</span>';
        echo '<h2>No active profile</h2>';
        echo '<p class="muted">Add an RSN profile to open your adventurer journal.</p>';
        echo '<a class="button" href="/profiles/new.php">Add profile</a>';
        echo '</div>';
        echo '</aside>';
        return;
    }

    $profileId = (int)$profile['id'];
    $metrics = null;
    $questCounts = [];
    $topSkills = [];
    $interests = [];

    try { $metrics = runemetrics_profile_metrics($profileId); } catch (Throwable $e) { $metrics = null; }
    try { $questCounts = quest_status_counts($profileId); } catch (Throwable $e) { $questCounts = []; }
    try { $topSkills = wf_sidebar_top_skills($profileId, 99); } catch (Throwable $e) { $topSkills = []; }
    try { $interests = profile_interest_tags($profileId); } catch (Throwable $e) { $interests = []; }

    $completedQuests = 0;
    $totalQuestRows = 0;
    foreach ($questCounts as $row) {
        $count = (int)($row['total'] ?? 0);
        $totalQuestRows += $count;
        if (str_contains(strtolower((string)($row['status'] ?? '')), 'complete')) {
            $completedQuests += $count;
        }
    }

    echo '<aside class="player-codex-sidebar">';
    echo '<div class="codex-sidebar-card character-sheet-card">';
    echo '<span class="codex-kicker">Active Adventurer</span>';
    echo '<div class="codex-character-head">';
    echo '<img class="codex-character-avatar" src="' . e(runescape_avatar_url((string)$profile['rsn'])) . '" alt="Avatar for ' . e((string)$profile['rsn']) . '" loading="lazy" referrerpolicy="no-referrer">';
    echo '<div><h2>' . e((string)($metrics['display_name'] ?? $profile['rsn'])) . '</h2>';
    echo '<p>' . e(account_type_options()[$profile['account_type']] ?? (string)$profile['account_type']) . '</p></div>';
    echo '</div>';

    echo '<div class="codex-stat-runes">';
    echo '<div><span>Total</span><strong>' . e(format_number_short($metrics['total_level'] ?? null)) . '</strong></div>';
    echo '<div><span>Combat</span><strong>' . e(format_number_short($metrics['combat_level'] ?? null)) . '</strong></div>';
    echo '<div><span>Quests</span><strong>' . ($totalQuestRows > 0 ? e($completedQuests . '/' . $totalQuestRows) : '—') . '</strong></div>';
    echo '</div>';

    echo '<div class="codex-sidebar-section">';
    echo '<h3>Mastered Arts</h3>';
    if ($topSkills) {
        echo '<div class="codex-skill-list">';
        foreach ($topSkills as $skill) {
            $display = rs3_display_level((string)($skill['skill_name'] ?? ''), $skill['level'] ?? null, $skill['xp'] ?? null);
            $skillName = (string)($skill['skill_name'] ?? 'Skill');
            echo '<div class="codex-skill-pill" title="' . e($skillName . ' level ' . (string)$display['display_level']) . '">';
            echo '<img src="' . e(wf_skill_icon_url($skillName)) . '" alt="' . e($skillName) . '" loading="lazy">';
            echo '<span>' . e($skillName) . '</span>';
            echo '<strong>' . e((string)$display['display_level']) . '</strong>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="muted small">Skill data appears here after RuneMetrics sync.</p>';
    }
    echo '</div>';

    echo '<div class="codex-sidebar-section">';
    echo '<h3>Interests</h3>';
    if ($interests) {
        echo '<div class="codex-tag-cloud">';
        foreach (array_slice($interests, 0, 8) as $tag) {
            echo '<span class="badge">' . e((string)$tag['name']) . '</span>';
        }
        echo '</div>';
    } else {
        echo '<p class="muted small">No interests selected yet.</p>';
    }
    echo '</div>';

    echo '<div class="codex-sidebar-actions">';
    echo '<a class="button secondary" href="/profiles/view.php?id=' . $profileId . '">Profile journal</a>';
    echo '<a class="button secondary" href="/profiles/edit.php?id=' . $profileId . '">Edit interests</a>';
    echo '</div>';
    echo '</div>';
    echo '</aside>';
}

function page_header(string $title): void
{
    $user = current_user();
    $isAdminContext = wf_is_admin_context();
    $bodyClass = $isAdminContext ? 'admin-layout' : 'player-layout';
    echo '<!doctype html><html lang="en-AU"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' - ' . e(env('APP_NAME', 'RS3 Wayfinder')) . '</title><link rel="stylesheet" href="/assets/app.css?v=flat-torn-cards-v1"><link rel="icon" type="image/png" href="/assets/branding/icon.png"></head><body class="' . e($bodyClass) . '">';
    echo '<header class="topbar"><a class="brand" href="/index.php"><img src="/assets/branding/icon.png" alt="Wayfinder" style="height:32px;width:32px;vertical-align:middle;margin-right:10px;border-radius:8px;">RS3 Wayfinder</a><nav>';
    if ($user) {
        echo '<a href="/index.php">Codex</a>';
        echo '<a href="/account/index.php">Account</a>';
        echo '<a href="/journeys/index.php">Journeys</a>';
        echo '<a href="/boss-log/index.php">Boss Log</a>';
        echo '<a href="/achievements/index.php">Achievements</a>';

        $profiles = profiles_for_user((int)$user['id']);
        if ($profiles) {
            $activeProfile = active_profile();
            echo '<form class="profile-selector-form" method="post" action="/profiles/select.php">';
            echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
            echo '<label class="sr-only" for="active-profile-select">Active profile</label>';
            echo '<select id="active-profile-select" name="profile_id" class="profile-selector" onchange="this.form.submit()">';
            foreach ($profiles as $profile) {
                $selected = ($activeProfile && (int)$activeProfile['id'] === (int)$profile['id']) ? ' selected' : '';
                $label = $profile['rsn'];
                if (!empty($profile['is_primary'])) {
                    $label .= ' ★';
                }
                echo '<option value="' . (int)$profile['id'] . '"' . $selected . '>' . e($label) . '</option>';
            }
            echo '</select>';
            echo '<noscript><button type="submit">Switch</button></noscript>';
            echo '</form>';
        }

        if (current_user_can('admin.access')) {
            echo '<div class="nav-dropdown">';
            echo '<span class="nav-dropdown-toggle">Admin ▾</span>';
            echo '<div class="nav-dropdown-menu">';
            echo '<a href="/admin/index.php">Admin Dashboard</a>';
            if (current_user_can('users.view')) echo '<a href="/admin/users.php">Users</a>';
            if (current_user_can('roles.manage')) echo '<a href="/admin/roles.php">Roles & Permissions</a>';
            if (current_user_can('profiles.view')) echo '<a href="/admin/profiles.php">Player Profiles</a>';
            if (current_user_can('journeys.view')) echo '<a href="/admin/journeys.php">Journeys</a>';
            if (current_user_can('content.view')) echo '<a href="/admin/content.php">Content Library</a>';
            echo '</div>';
            echo '</div>';
        }
        echo '<a href="/auth/logout.php">Logout</a>';
    } else {
        echo '<a href="/auth/login.php">Login</a>';
    }
    echo '</nav></header>';

    if ($user && !$isAdminContext) {
        echo '<main class="container player-book-shell">';
        wf_render_player_codex_sidebar(active_profile());
        echo '<section class="player-book-page">';
    } else {
        echo '<main class="container">';
    }
}

function page_footer(): void
{
    $user = current_user();
    $isAdminContext = wf_is_admin_context();
    if ($user && !$isAdminContext) {
        echo '</section>';
    }
    echo '</main><div id="global-loading-overlay" class="global-loading-overlay" aria-live="polite" aria-hidden="true"><div class="global-loading-card"><span class="rs-spinner"></span><strong>Loading...</strong><small>Wayfinder is working on that request.</small></div></div><footer class="footer"><span>RS3 Wayfinder is an independent RuneScape journey tool and is not affiliated with Jagex or Discord.</span><a class="footer-discord-link" href="https://discord.gg/h542k9xg6Y" target="_blank" rel="noopener noreferrer" aria-label="Join the RS3 Wayfinder Discord"><svg class="footer-discord-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20.32 4.37A19.8 19.8 0 0 0 15.36 2.8a13.6 13.6 0 0 0-.64 1.33 18.3 18.3 0 0 0-5.44 0 13.6 13.6 0 0 0-.64-1.33 19.8 19.8 0 0 0-4.96 1.57C.55 9.02-.32 13.55.1 18.02a19.9 19.9 0 0 0 6.08 3.08c.49-.67.93-1.38 1.31-2.13a12.9 12.9 0 0 1-2.06-.99c.17-.12.34-.25.5-.38a14.2 14.2 0 0 0 12.14 0l.5.38c-.66.39-1.35.72-2.06.99.38.75.82 1.46 1.31 2.13a19.9 19.9 0 0 0 6.08-3.08c.5-5.18-.84-9.67-3.58-13.65ZM8.02 15.28c-1.18 0-2.15-1.08-2.15-2.41s.95-2.42 2.15-2.42 2.17 1.09 2.15 2.42c0 1.33-.95 2.41-2.15 2.41Zm7.96 0c-1.18 0-2.15-1.08-2.15-2.41s.95-2.42 2.15-2.42 2.17 1.09 2.15 2.42c0 1.33-.95 2.41-2.15 2.41Z"/></svg><span>Join Discord</span></a></footer><script>
document.addEventListener("DOMContentLoaded", function () {
    const overlay = document.getElementById("global-loading-overlay");
    function showLoading(message) {
        if (!overlay) return;
        const strong = overlay.querySelector("strong");
        if (strong && message) strong.textContent = message;
        overlay.classList.add("is-visible");
        overlay.setAttribute("aria-hidden", "false");
    }
    document.querySelectorAll("form").forEach(function(form) {
        form.addEventListener("submit", function(event) {
            if (event.defaultPrevented || form.dataset.noLoading === "1") return;
            const submitter = event.submitter;
            const label = submitter ? (submitter.getAttribute("data-loading-text") || submitter.textContent || "Loading...") : "Loading...";
            if (submitter && submitter.tagName === "BUTTON") {
                submitter.classList.add("is-loading");
                submitter.disabled = true;
            }
            showLoading(label.trim() || "Loading...");
        });
    });
    document.querySelectorAll("a.button, a[data-loading-link]").forEach(function(link) {
        link.addEventListener("click", function(event) {
            if (event.defaultPrevented || link.target === "_blank" || link.dataset.noLoading === "1" || link.href.indexOf("#") === link.href.length - 1) return;
            link.classList.add("is-loading");
            showLoading(link.getAttribute("data-loading-text") || "Loading...");
        });
    });
    document.querySelectorAll("select.searchable-select").forEach(function (select) {
        if (select.dataset.enhanced === "1") return;
        select.dataset.enhanced = "1";

        select.style.display = "none";

        const wrapper = document.createElement("div");
        wrapper.className = "searchable-dropdown";

        const input = document.createElement("input");
        input.type = "text";
        input.className = "searchable-dropdown-input";
        input.placeholder = "Search and select...";
        input.autocomplete = "off";

        const list = document.createElement("div");
        list.className = "searchable-dropdown-list";

        const selectedOption = select.options[select.selectedIndex];
        if (selectedOption) input.value = selectedOption.text;

        function render(term = "") {
            list.innerHTML = "";
            const filtered = Array.from(select.options).filter(function(option){
                return option.text.toLowerCase().includes(term.toLowerCase());
            });

            filtered.slice(0, 100).forEach(function(option){
                const item = document.createElement("button");
                item.type = "button";
                item.className = "searchable-dropdown-item";
                item.textContent = option.text;
                item.addEventListener("click", function(){
                    select.value = option.value;
                    input.value = option.text;
                    list.classList.remove("is-open");
                    select.dispatchEvent(new Event("change"));
                });
                list.appendChild(item);
            });
        }

        input.addEventListener("focus", function(){
            render(input.value);
            list.classList.add("is-open");
        });

        input.addEventListener("input", function(){
            render(input.value);
            list.classList.add("is-open");
        });

        document.addEventListener("click", function(e){
            if (!wrapper.contains(e.target)) {
                list.classList.remove("is-open");
            }
        });

        wrapper.appendChild(input);
        wrapper.appendChild(list);
        select.parentNode.insertBefore(wrapper, select);
    });
});
</script>
</body></html>';
}
