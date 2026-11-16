<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Src\Prompt\Actions\GetTailwindSetupAction;
use Src\Prompt\Objects\MockupObject;
use Src\Prompt\Objects\PromptRunId;
use Src\Prompt\Objects\TailwindSetupObject;

/**
 * A screen the agent drew, shown in a frame of its own with the stylesheets
 * of the project. The frame runs no script of the agent: only the one line
 * that tells the page how tall the screen is.
 *
 * The frame keeps the origin of DevDock, because a browser does not apply
 * stylesheets to a frame without one; the policy inside it is what keeps the
 * markup harmless.
 *
 * In a project with Tailwind the frame also runs Tailwind itself, with the
 * project's theme: the built stylesheet only holds the classes the project
 * already used, so a class that is new in the sketch would otherwise do nothing.
 */
class Mockup extends Component
{
	private const TAILWIND = 'https://cdn.tailwindcss.com/3.4.17';

	public function __construct(
		public MockupObject $mockup,
		public int $run,
	) {}

	public function render(): View
	{
		return view('components.mockup', [
			'document' => $this->document(),
			'stylesheetNames' => collect($this->stylesheets())->map(fn (string $stylesheet) => basename((string) parse_url($stylesheet, PHP_URL_PATH)))->implode(', '),
		]);
	}

	/**
	 * The page inside the frame. The nonce comes from the markup, so a page
	 * that is drawn again is the same page and the frame does not reload.
	 */
	private function document(): string
	{
		$origin = request()->getSchemeAndHttpHost();
		$nonce = md5($this->mockup->html);

		$links = collect($this->stylesheets())
			->map(fn (string $stylesheet) => '<link rel="stylesheet" href="' . e($stylesheet) . '">')
			->implode('');

		$tailwindSetupObject = GetTailwindSetupAction::execute(new PromptRunId(id: $this->run));

		return '<!doctype html><html><head><meta charset="utf-8">'
			// the policy comes first, so nothing the agent wrote can loosen it: no script
			// but ours and Tailwind's, no image beyond the files of the project, no form,
			// no frame; stylesheets and fonts may come from a font service, as layouts often do
			. '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src ' . $origin . ' https: \'unsafe-inline\'; font-src ' . $origin . ' https: data:; img-src ' . $origin . ' data:; script-src \'nonce-' . $nonce . '\' ' . self::TAILWIND . '; base-uri ' . $origin . '; form-action \'none\'">'
			. '<base href="' . e(url('agent/' . $this->run . '/public')) . '/">'
			. $links
			. ($tailwindSetupObject === null ? '' : $this->tailwind($tailwindSetupObject, $nonce))
			// a layout stylesheet often stretches the body to the screen; here it follows the content
			. '<style>html,body{height:auto !important;min-height:0 !important;margin:0;background:#fff}#mockup-root{display:flow-root;padding:16px}</style>'
			. '</head><body><div id="mockup-root">' . $this->mockup->html . '</div>'
			. '<script nonce="' . $nonce . '">'
			. 'const root=document.getElementById("mockup-root");'
			. 'const tell=()=>parent.postMessage({mockupHeight:Math.ceil(root.getBoundingClientRect().height)},"*");'
			. 'new ResizeObserver(tell).observe(root);addEventListener("load",tell);tell();'
			// the panel around the frame may still be arriving, and Tailwind styles the page a moment after it loads
			. 'let told=0;const again=setInterval(()=>{tell();if(++told>=10){clearInterval(again);}},300);'
			. '</script></body></html>';
	}

	/**
	 * Tailwind running in the frame with the theme and plugins of the project.
	 * The project's own stylesheet already brings the base styles, so Tailwind
	 * leaves those out when one is loaded.
	 */
	private function tailwind(TailwindSetupObject $tailwindSetupObject, string $nonce): string
	{
		$plugins = $tailwindSetupObject->plugins === [] ? '' : '?plugins=' . implode(',', $tailwindSetupObject->plugins);
		$config = [
			'theme' => (object) $tailwindSetupObject->theme,
			'corePlugins' => ['preflight' => $this->stylesheets() === []],
		];

		return '<script src="' . self::TAILWIND . $plugins . '"></script>'
			. '<script nonce="' . $nonce . '">tailwind.config=' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ';</script>';
	}

	/**
	 * The stylesheets as paths inside the public folder, however the agent
	 * wrote them down.
	 *
	 * @return array<int, string>
	 */
	private function stylesheets(): array
	{
		return collect($this->mockup->stylesheets)
			->map(fn (string $stylesheet) => (string) preg_replace('#^/?(public/)?#', '', str_replace('\\', '/', $stylesheet)))
			->filter(fn (string $stylesheet) => str_ends_with(strtolower(strtok($stylesheet, '?') ?: ''), '.css'))
			->values()
			->all();
	}
}
