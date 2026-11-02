// Kleurt de code in een diff, regel voor regel, met de klassieke kleuren van
// een editor. Alleen de talen die in de projecten voorkomen gaan mee, zodat
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

			code.innerHTML = language !== 'markdown' && language !== 'xml' && COMMENT_LINE.test(text)
				? `<span class="hljs-comment">${hljs.highlight(text, { language: 'plaintext' }).value}</span>`
				: hljs.highlight(text, { language, ignoreIllegals: true }).value;
			code.dataset.lit = '';
		}
	}
}

document.addEventListener('DOMContentLoaded', () => highlightDiffs());

// Livewire zet bij een nieuwe weergave de platte tekst terug; daarna kleurt het opnieuw
document.addEventListener('livewire:init', () => {
	Livewire.hook('morphed', ({ el }) => highlightDiffs(el));
	Livewire.hook('commit', ({ succeed }) => succeed(() => requestAnimationFrame(() => highlightDiffs())));
});
