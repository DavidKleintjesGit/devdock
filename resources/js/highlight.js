// Kleurt de code in een diff, regel voor regel, en de codeblokken in de chat
// en de rapporten, met de klassieke kleuren van een editor. Alleen de talen die in de projecten voorkomen gaan mee, zodat
// het pakket klein blijft.
import hljs from 'highlight.js/lib/core';
import bash from 'highlight.js/lib/languages/bash';
import css from 'highlight.js/lib/languages/css';
import javascript from 'highlight.js/lib/languages/javascript';
import json from 'highlight.js/lib/languages/json';
import markdown from 'highlight.js/lib/languages/markdown';
import php from 'highlight.js/lib/languages/php';
import scss from 'highlight.js/lib/languages/scss';
import sql from 'highlight.js/lib/languages/sql';
import typescript from 'highlight.js/lib/languages/typescript';
import xml from 'highlight.js/lib/languages/xml';
import yaml from 'highlight.js/lib/languages/yaml';

hljs.registerLanguage('bash', bash);
hljs.registerLanguage('css', css);
hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('json', json);
hljs.registerLanguage('markdown', markdown);
hljs.registerLanguage('php', php);
hljs.registerLanguage('plaintext', () => ({ name: 'plaintext', contains: [] }));
hljs.registerLanguage('scss', scss);
hljs.registerLanguage('sql', sql);
hljs.registerLanguage('typescript', typescript);
hljs.registerLanguage('xml', xml);
hljs.registerLanguage('yaml', yaml);

const LANGUAGES = [
	// een Blade-view is vooral HTML; de @-regels kleurt geen enkele taal goed
	[/\.blade\.php$/, 'xml'],
	[/\.php$/, 'php'],
	[/\.(m?js|cjs|jsx)$/, 'javascript'],
	[/\.tsx?$/, 'typescript'],
	[/\.scss$/, 'scss'],
	[/\.css$/, 'css'],
	[/\.json$/, 'json'],
	[/\.md$/, 'markdown'],
	[/\.ya?ml$/, 'yaml'],
	[/\.sql$/, 'sql'],
	[/\.(sh|bash)$/, 'bash'],
	[/\.(html|vue|xml|svg)$/, 'xml'],
];

// een losse regel uit een docblock of commentaar is zonder zijn begin niet als
// commentaar te herkennen; dit vangt de gewone vormen op
const COMMENT_LINE = /^\s*(\/\/|#(?!\[)|\/\*|\*|\*\/)/;

// colouring a minified line takes longer than the page can wait; it stays plain
const MAX_LIT_CHARACTERS = 1000;

function languageOf(file) {
	return LANGUAGES.find(([pattern]) => pattern.test(file))?.[1] ?? null;
}

function highlightDiffs(root = document) {
	for (const diff of root.querySelectorAll('.diff[data-highlight]')) {
		const language = languageOf(diff.dataset.highlight.toLowerCase());

		if (language === null) {
			continue;
		}

		for (const code of diff.querySelectorAll('.dl .code:not([data-lit])')) {
			const text = code.textContent;
			code.dataset.lit = '';

			if (text.length > MAX_LIT_CHARACTERS) {
				continue;
			}

			code.innerHTML = language !== 'markdown' && language !== 'xml' && COMMENT_LINE.test(text)
				? `<span class="hljs-comment">${hljs.highlight(text, { language: 'plaintext' }).value}</span>`
				: hljs.highlight(text, { language, ignoreIllegals: true }).value;
		}
	}

	markChangedParts(root);
}

// The part of a line that changed comes from the server as a character range;
// it is laid over the colouring afterwards, so both stay.
function markChangedParts(root) {
	for (const code of root.querySelectorAll('.diff .dl .code[data-mark-start]:not([data-marked])')) {
		const start = Number(code.dataset.markStart);
		const end = Number(code.dataset.markEnd);
		const walker = document.createTreeWalker(code, NodeFilter.SHOW_TEXT);
		const pieces = [];
		let offset = 0;

		while (walker.nextNode()) {
			const node = walker.currentNode;
			const from = Math.max(start - offset, 0);
			const to = Math.min(end - offset, node.length);

			if (from < to) {
				pieces.push([node, from, to]);
			}

			offset += node.length;
		}

		for (const [node, from, to] of pieces) {
			const range = document.createRange();
			range.setStart(node, from);
			range.setEnd(node, to);
			range.surroundContents(Object.assign(document.createElement('mark'), { className: 'wd' }));
		}

		code.dataset.marked = '';
	}
}

// A code block in the chat or a report is coloured in the language its
// backticks name, or in the language that fits it best when they name none.
const BLOCK_LANGUAGES = { js: 'javascript', ts: 'typescript', sh: 'bash', shell: 'bash', html: 'xml', blade: 'xml', vue: 'xml', yml: 'yaml', md: 'markdown' };

function highlightBlocks(root = document) {
	for (const block of root.querySelectorAll('.md .code:not([data-lit])')) {
		const text = block.textContent;
		const named = BLOCK_LANGUAGES[block.dataset.language] ?? block.dataset.language;
		block.dataset.lit = '';

		if (text.length > MAX_LIT_CHARACTERS * 20) {
			continue;
		}

		block.innerHTML = named && hljs.getLanguage(named)
			? hljs.highlight(text, { language: named, ignoreIllegals: true }).value
			: hljs.highlightAuto(text).value;
	}
}

function highlightAll(root = document) {
	highlightDiffs(root);
	highlightBlocks(root);
}

document.addEventListener('DOMContentLoaded', () => highlightAll());

// Livewire zet bij een nieuwe weergave de platte tekst terug; daarna kleurt het opnieuw
document.addEventListener('livewire:init', () => {
	Livewire.hook('morphed', ({ el }) => highlightAll(el));
	Livewire.hook('commit', ({ succeed }) => succeed(() => requestAnimationFrame(() => highlightAll())));
});
