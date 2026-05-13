<?php
declare(strict_types=1);

function page_header(string $title): void
{
    $user = current_user();
    echo '<!doctype html><html lang="en-AU"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' - ' . e(env('APP_NAME', 'RS3 Wayfinder')) . '</title><link rel="stylesheet" href="/assets/app.css"><link rel="icon" type="image/png" href="/assets/branding/icon.png"></head><body>';
    echo '<header class="topbar"><a class="brand" href="/index.php"><img src="/assets/branding/icon.png" alt="Wayfinder" style="height:32px;width:32px;vertical-align:middle;margin-right:10px;border-radius:8px;">RS3 Wayfinder</a><nav>';
    if ($user) {
        echo '<a href="/dashboard.php">Dashboard</a>';
        echo '<a href="/profiles/index.php">Profiles</a>';
        echo '<a href="/journeys/index.php">Journeys</a>';

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
    echo '</nav></header><main class="container">';
}

function page_footer(): void
{
    echo '</main><footer class="footer">RS3 Wayfinder is an independent RuneScape journey tool and is not affiliated with Jagex or Discord.</footer><script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("select.searchable-select").forEach(function (select) {
        if (select.dataset.searchReady === "1") return;
        select.dataset.searchReady = "1";

        const wrapper = document.createElement("div");
        wrapper.className = "searchable-select-wrap";

        const input = document.createElement("input");
        input.type = "search";
        input.className = "searchable-select-input";
        input.placeholder = "Search options…";
        input.setAttribute("aria-label", "Search options");

        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(input);
        wrapper.appendChild(select);

        const allOptions = Array.from(select.options).map(function (option) {
            return {
                value: option.value,
                text: option.text,
                selected: option.selected,
                dataset: Object.assign({}, option.dataset)
            };
        });

        input.addEventListener("input", function () {
            const term = input.value.trim().toLowerCase();
            const currentValue = select.value;
            select.innerHTML = "";

            allOptions.forEach(function (item) {
                if (term && !item.text.toLowerCase().includes(term)) return;

                const option = document.createElement("option");
                option.value = item.value;
                option.text = item.text;
                Object.keys(item.dataset).forEach(function (key) {
                    option.dataset[key] = item.dataset[key];
                });
                if (item.value === currentValue) option.selected = true;
                select.appendChild(option);
            });

            if (!select.options.length) {
                const option = document.createElement("option");
                option.value = "";
                option.text = "No matching results";
                select.appendChild(option);
            }
        });
    });
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
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
