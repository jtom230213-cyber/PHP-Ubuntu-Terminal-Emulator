<?php
declare(strict_types=1);

/**
 * PHP Ubuntu Terminal Emulator
 * Main entry point - serves the web UI and handles API requests.
 */

session_name('php_terminal');
session_start();

require_once __DIR__ . '/init.php';

// ─── API mode ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    try {
        $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        $command = trim($input['command'] ?? '');
        $cwd = trim($input['cwd'] ?? '/home/visitor');

        if ($command === '') {
            echo json_encode(['output' => '', 'cwd' => $cwd, 'exit' => false]);
            exit;
        }

        $fs = getFilesystem();
        $env = $_SESSION['php_terminal_env'] ?? [];
        $result = executeCommand($command, $fs, $cwd, $env);
        saveFilesystem($fs);

        echo json_encode([
            'output' => $result->output,
            'cwd' => $result->cwd !== '' ? $result->cwd : $cwd,
            'clear' => $result->clear,
            'error' => $result->error,
            'exit' => $result->exit,
        ], JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        echo json_encode([
            'output' => "Internal error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'),
            'cwd' => $cwd ?? '/home/visitor',
            'error' => true,
        ]);
    }
    exit;
}

// ─── HTML UI mode ──────────────────────────────────────────────────────────
$motd = getMotd();
$hostname = 'php-terminal';
$username = $_SESSION['php_terminal_user'] ?? 'visitor';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PHP Ubuntu Terminal Emulator</title>
<style>
/* ── Reset & Base ────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg: #1a1a2e;
    --bg-term: #0d0d1a;
    --fg: #e0e0e0;
    --fg-dim: #6c6c8a;
    --green: #00ff88;
    --amber: #ffb347;
    --red: #ff4757;
    --cyan: #00d4ff;
    --purple: #a855f7;
    --pink: #ff6b9d;
    --orange: #ff8c42;
    --blue: #4dabf7;
    --titlebar: #16213e;
    --border: #2a2a4a;
    --prompt: #00ff88;
    --selection: rgba(0, 255, 136, 0.15);
    --radius: 12px;
    --shadow: 0 20px 60px rgba(0,0,0,0.5);
}

html, body {
    height: 100%;
    font-family: 'Cascadia Code', 'Fira Code', 'JetBrains Mono', 'Consolas', 'Courier New', monospace;
    background: var(--bg);
    color: var(--fg);
    overflow: hidden;
}

/* ── Background ──────────────────────────────────────────────── */
body {
    display: flex;
    align-items: center;
    justify-content: center;
    background:
        radial-gradient(ellipse at 20% 50%, rgba(168, 85, 247, 0.08) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 50%, rgba(0, 212, 255, 0.08) 0%, transparent 60%),
        radial-gradient(ellipse at 50% 0%, rgba(0, 255, 136, 0.05) 0%, transparent 50%),
        var(--bg);
}

/* ── Terminal Window ─────────────────────────────────────────── */
.terminal-wrapper {
    width: 95vw;
    max-width: 1200px;
    height: 90vh;
    max-height: 900px;
    background: var(--bg-term);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative;
}

/* ── Title Bar ───────────────────────────────────────────────── */
.titlebar {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    background: var(--titlebar);
    border-bottom: 1px solid var(--border);
    user-select: none;
    flex-shrink: 0;
}

.titlebar-dots {
    display: flex;
    gap: 8px;
    margin-right: 16px;
}

.titlebar-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    transition: filter 0.2s;
}

.titlebar-dot:hover { filter: brightness(1.3); }

.titlebar-dot.red { background: #ff5f57; }
.titlebar-dot.amber { background: #ffbd2e; }
.titlebar-dot.green { background: #28c840; }

.titlebar-title {
    flex: 1;
    text-align: center;
    font-size: 13px;
    color: var(--fg-dim);
    letter-spacing: 0.5px;
    font-weight: 500;
}

.titlebar-title span {
    color: var(--green);
}

/* ── Terminal Output Area ────────────────────────────────────── */
.terminal-output {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    line-height: 1.6;
    font-size: 14px;
    scroll-behavior: smooth;
    position: relative;
}

.terminal-output::-webkit-scrollbar {
    width: 6px;
}
.terminal-output::-webkit-scrollbar-track {
    background: transparent;
}
.terminal-output::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 3px;
}
.terminal-output::-webkit-scrollbar-thumb:hover {
    background: var(--fg-dim);
}

/* ── Output Lines ────────────────────────────────────────────── */
.line {
    white-space: pre-wrap;
    word-break: break-all;
    min-height: 1.2em;
    animation: fadeIn 0.15s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-2px); }
    to { opacity: 1; transform: translateY(0); }
}

.line.error {
    color: var(--red);
}

.line.success {
    color: var(--green);
}

.line.info {
    color: var(--cyan);
}

.line.warning {
    color: var(--amber);
}

.line.dim {
    color: var(--fg-dim);
}

.line.prompt {
    color: var(--green);
    display: inline;
}

/* ── MOTD Styling ────────────────────────────────────────────── */
.line.motd-ascii {
    color: var(--purple);
    font-size: 11px;
    line-height: 1.2;
}

.line.motd-title {
    color: var(--cyan);
    font-weight: bold;
    font-size: 16px;
    margin: 8px 0 4px;
}

.line.motd-section {
    color: var(--amber);
    margin-top: 4px;
}

.line.motd-info {
    color: var(--fg-dim);
    padding-left: 2ch;
}

/* ── Command Input Line ──────────────────────────────────────── */
.input-line {
    display: flex;
    align-items: center;
    padding: 4px 20px 20px;
    position: relative;
}

.input-prompt {
    color: var(--green);
    font-size: 14px;
    white-space: pre;
    flex-shrink: 0;
    user-select: none;
}

.input-prompt .at { color: var(--cyan); }
.input-prompt .colon { color: var(--fg-dim); }
.input-prompt .dollar { color: var(--green); font-weight: bold; }

.input-field {
    background: transparent;
    border: none;
    color: var(--fg);
    font-family: inherit;
    font-size: 14px;
    outline: none;
    flex: 1;
    margin-left: 2px;
    caret-color: var(--green);
}

.input-field::selection {
    background: var(--selection);
}

/* ── Status Bar ──────────────────────────────────────────────── */
.statusbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 20px;
    background: var(--titlebar);
    border-top: 1px solid var(--border);
    font-size: 11px;
    color: var(--fg-dim);
    flex-shrink: 0;
}

.statusbar-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.statusbar-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--green);
    box-shadow: 0 0 6px var(--green);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width: 768px) {
    .terminal-wrapper {
        width: 100vw;
        height: 100vh;
        max-height: none;
        border-radius: 0;
        border: none;
    }
    .terminal-output {
        padding: 12px;
        font-size: 12px;
    }
    .input-line {
        padding: 4px 12px 12px;
    }
    .input-field, .input-prompt {
        font-size: 12px;
    }
    .titlebar {
        padding: 8px 12px;
    }
    .titlebar-dot {
        width: 10px;
        height: 10px;
    }
    .line.motd-ascii {
        font-size: 8px;
    }
    .statusbar {
        padding: 4px 12px;
        font-size: 10px;
    }
}

@media (max-width: 480px) {
    .terminal-output {
        padding: 8px;
        font-size: 11px;
    }
    .input-line {
        padding: 4px 8px 8px;
    }
    .input-field, .input-prompt {
        font-size: 11px;
    }
    .line.motd-ascii {
        font-size: 6px;
    }
    .titlebar-title {
        font-size: 11px;
    }
}

/* ── Scrollbar for Windows ───────────────────────────────────── */
@media (hover: none) {
    .terminal-output::-webkit-scrollbar {
        width: 3px;
    }
}
</style>
</head>
<body>

<div class="terminal-wrapper">
    <!-- Title Bar -->
    <div class="titlebar">
        <div class="titlebar-dots">
            <div class="titlebar-dot red" title="Close"></div>
            <div class="titlebar-dot amber" title="Minimize"></div>
            <div class="titlebar-dot green" title="Maximize"></div>
        </div>
        <div class="titlebar-title">
            <span>visitor@php-terminal</span>:~ — PHP Ubuntu Terminal Emulator
        </div>
    </div>

    <!-- Output Area -->
    <div id="output" class="terminal-output"></div>

    <!-- Input Line -->
    <div class="input-line">
        <span id="prompt" class="input-prompt">
            <span class="user">visitor</span>
            <span class="at">@</span>
            <span class="host">php-terminal</span>
            <span class="colon">:</span>
            <span class="path">~</span>
            <span class="dollar"> $ </span>
        </span>
        <input
            type="text"
            id="commandInput"
            class="input-field"
            autofocus
            spellcheck="false"
            autocomplete="off"
            placeholder="Type a command or 'help'..."
            aria-label="Command input"
        />
    </div>

    <!-- Status Bar -->
    <div class="statusbar">
        <div class="statusbar-item">
            <span class="statusbar-indicator"></span>
            <span>Connected</span>
        </div>
        <div class="statusbar-item">
            <span id="statusCommand">Ready</span>
        </div>
        <div class="statusbar-item">
            <span id="statusTime"></span>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // ─── Elements ───────────────────────────────────────────────
    const output = document.getElementById('output');
    const input = document.getElementById('commandInput');
    const promptEl = document.getElementById('prompt');
    const statusTime = document.getElementById('statusTime');
    const statusCommand = document.getElementById('statusCommand');

    // ─── State ──────────────────────────────────────────────────
    let currentCwd = '/home/visitor';
    let history = [];
    let historyIndex = -1;
    let tabBuffer = '';
    let isProcessing = false;

    // ─── Update clock ───────────────────────────────────────────
    function updateClock() {
        const now = new Date();
        statusTime.textContent = now.toLocaleTimeString();
    }
    updateClock();
    setInterval(updateClock, 1000);

    // ─── Sanitize HTML ──────────────────────────────────────────
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ─── Update prompt ──────────────────────────────────────────
    function updatePrompt(cwd) {
        const home = '/home/visitor';
        const displayPath = cwd === home ? '~' : cwd.replace(home, '~');
        const parts = displayPath.split('/');
        const shortPath = parts.length > 3
            ? parts.slice(0, 2).join('/') + '/.../' + parts.slice(-1)
            : displayPath;

        promptEl.innerHTML =
            '<span class="user">visitor</span>' +
            '<span class="at">@</span>' +
            '<span class="host">php-terminal</span>' +
            '<span class="colon">:</span>' +
            '<span class="path">' + escapeHtml(shortPath) + '</span>' +
            '<span class="dollar"> $ </span>';
    }

    // ─── Add a line to the output ───────────────────────────────
    function addLine(text, className = '') {
        const line = document.createElement('div');
        line.className = 'line' + (className ? ' ' + className : '');
        line.textContent = text;
        output.appendChild(line);
        scrollToBottom();
    }

    // ─── Scroll to bottom ───────────────────────────────────────
    function scrollToBottom() {
        requestAnimationFrame(() => {
            output.scrollTop = output.scrollHeight;
        });
    }

    // ─── Show MOTD ──────────────────────────────────────────────
    function showMotd() {
        const motdLines = <?php echo json_encode(explode("\n", $motd)); ?>;
        let inSystemInfo = false;

        for (const line of motdLines) {
            if (line.startsWith('██') || line.startsWith('╚═') || line.startsWith('  ██') ||
                line.startsWith(' ██') || line.startsWith('    ██') || line.startsWith('    ╚═')) {
                addLine(line, 'motd-ascii');
                continue;
            }

            if (line.startsWith('Welcome')) {
                addLine(line, 'motd-title');
                continue;
            }

            if (line.startsWith('System')) {
                addLine(line, 'motd-section');
                inSystemInfo = true;
                continue;
            }

            if (inSystemInfo && (line.startsWith('  ') || line.startsWith('Type'))) {
                addLine(line, 'motd-info');
                if (line.startsWith('Type')) {
                    inSystemInfo = false;
                }
                continue;
            }

            if (line.trim() === '') {
                addLine('');
                continue;
            }

            addLine(line, 'dim');
        }
    }

    // ─── Send command to server ─────────────────────────────────
    async function sendCommand(command) {
        if (isProcessing) return;
        isProcessing = true;
        input.disabled = true;
        statusCommand.textContent = 'Running: ' + command;

        try {
            const response = await fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    command: command,
                    cwd: currentCwd,
                }),
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const data = await response.json();

            // Handle clear
            if (data.clear) {
                output.innerHTML = '';
                currentCwd = data.cwd || currentCwd;
                updatePrompt(currentCwd);
                showMotd();
                isProcessing = false;
                input.disabled = false;
                input.focus();
                statusCommand.textContent = 'Ready';
                return;
            }

            // Handle exit
            if (data.exit) {
                addLine(data.output || '', 'info');
                addLine('[Session ended. Type anything to restart.]', 'warning');
                currentCwd = '/home/visitor';
                updatePrompt(currentCwd);
                isProcessing = false;
                input.disabled = false;
                input.focus();
                statusCommand.textContent = 'Ready';
                return;
            }

            // Normal output
            if (data.output) {
                const lines = data.output.split('\n');
                for (const line of lines) {
                    addLine(line, data.error ? 'error' : '');
                }
            }

            // Update cwd
            if (data.cwd) {
                currentCwd = data.cwd;
                updatePrompt(currentCwd);
            }

        } catch (err) {
            addLine('Connection error: ' + err.message, 'error');
        }

        isProcessing = false;
        input.disabled = false;
        input.focus();
        statusCommand.textContent = 'Ready';
    }

    // ─── Handle command input ───────────────────────────────────
    function handleCommand() {
        const command = input.value.trim();
        if (!command) return;

        const home = '/home/visitor';
        const displayPath = currentCwd === home ? '~' : currentCwd.replace(home, '~');
        addLine('visitor@php-terminal:' + displayPath + '$ ' + command, 'info');

        if (history.length === 0 || history[history.length - 1] !== command) {
            history.push(command);
        }
        historyIndex = history.length;

        input.value = '';
        tabBuffer = '';

        sendCommand(command);
    }

    // ─── Tab completion ─────────────────────────────────────────
    function handleTab() {
        const value = input.value;
        if (!value) return;

        const builtins = [
            'pwd','cd','ls','cat','echo','grep','man','help','clear',
            'whoami','id','date','cal','uname','hostname','uptime',
            'ps','top','kill','ping','ifconfig','netstat','ss',
            'chmod','chown','su','sudo','apt','tar','zip','unzip',
            'gzip','gunzip','ssh','scp','curl','wget','cp','mv',
            'rm','mkdir','touch','head','tail','wc','sort',
            'diff','find','locate','which','alias','export','source',
            'history','reset','exit','logout','shutdown','reboot',
            'systemctl','service','crontab','bc','expr','printf',
            'tree','ln','stat','du','df','tee','nl','od','xxd',
            'strings','sed','awk','less','more','watch','xargs',
            'yes','seq','sleep','timeout','nohup','nice','renice',
            'traceroute','nslookup','dig','host','telnet','nc',
            'nmap','iptables','route','arp','whois','lscpu','free',
            'lspci','lsusb','dmesg','locale','env','printenv',
            'adduser','useradd','passwd','umask','dpkg','snap',
            'bzip2','xz','zcat','zgrep','cowsay','fortune',
            'journalctl','screen','script','last','who','w',
            'users','groups','finger','arch','nproc','lsblk',
            'hostnamectl','timedatectl','logname','tty','dir',
            'pushd','popd','dirs','readlink','realpath','basename',
            'dirname','shred','mktemp','cut','tr','paste','column',
            'rev','tac','cmp','join','fold','fmt','whatis',
            'apropos','info','declare','readonly','eval','exec',
            'read','test','command','hash','unalias','unset','set',
            'type','bg','fg','jobs','pkill','killall',
            'ip','iwconfig','ethtool','mtr','rfkill','iwlist',
            'zless','zmore','zdiff','compress','uncompress',
            'bunzip2','unxz','chattr','lsattr','install','tempfile',
            'pathchk','dd','hexdump','patch','comm','look','tsort',
            'lastlog','lshw','getconf','parallel','ftp','snap',
            'flatpak','update-rc.d','init','halt','poweroff',
            'clear_console','banner','figlet','factor','at','batch',
            'sudoedit','visudo','tmux','unexpand','expand','pr',
            'nl',
        ];

        const partial = value.toLowerCase();
        const matches = builtins.filter(b => b.startsWith(partial) && b !== partial);

        if (matches.length === 1) {
            input.value = matches[0] + ' ';
            tabBuffer = '';
        } else if (matches.length > 1) {
            let common = matches[0];
            for (let i = 1; i < matches.length; i++) {
                while (matches[i].indexOf(common) !== 0) {
                    common = common.slice(0, -1);
                }
            }
            if (common !== partial) {
                input.value = common;
                tabBuffer = '';
            } else {
                if (tabBuffer === partial) {
                    addLine(matches.join('  '), 'dim');
                    const home = '/home/visitor';
                    const displayPath = currentCwd === home ? '~' : currentCwd.replace(home, '~');
                    addLine('visitor@php-terminal:' + displayPath + '$ ' + input.value, 'info');
                } else {
                    tabBuffer = partial;
                }
            }
        }
    }

    // ─── Keyboard handling ──────────────────────────────────────
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleCommand();
            return;
        }

        if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (historyIndex > 0) {
                historyIndex--;
                input.value = history[historyIndex] || '';
                input.setSelectionRange(input.value.length, input.value.length);
            }
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (historyIndex < history.length - 1) {
                historyIndex++;
                input.value = history[historyIndex] || '';
            } else {
                historyIndex = history.length;
                input.value = '';
            }
            input.setSelectionRange(input.value.length, input.value.length);
            return;
        }

        if (e.key === 'Tab') {
            e.preventDefault();
            handleTab();
            return;
        }

        if (e.key === 'l' && e.ctrlKey) {
            e.preventDefault();
            output.innerHTML = '';
            showMotd();
            const home = '/home/visitor';
            const displayPath = currentCwd === home ? '~' : currentCwd.replace(home, '~');
            addLine('visitor@php-terminal:' + displayPath + '$ ' + input.value, 'info');
            return;
        }

        if (e.key === 'c' && e.ctrlKey && input.value) {
            const home = '/home/visitor';
            const displayPath = currentCwd === home ? '~' : currentCwd.replace(home, '~');
            addLine('visitor@php-terminal:' + displayPath + '$ ' + input.value + '^C', 'dim');
            input.value = '';
            tabBuffer = '';
            return;
        }

        tabBuffer = '';
    });

    // ─── Focus handling ─────────────────────────────────────────
    output.addEventListener('click', function() { input.focus(); });
    input.focus();

    // ─── Initialize ─────────────────────────────────────────────
    showMotd();
    updatePrompt(currentCwd);
    scrollToBottom();

    // ─── Handle window resize ───────────────────────────────────
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(scrollToBottom, 100);
    });

})();
</script>
</body>
</html>