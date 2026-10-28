<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Src\Board\Providers\BoardServiceProvider;
use Src\Fetch\Providers\FetchServiceProvider;
use Src\Project\Providers\ProjectServiceProvider;
use Src\Prompt\Providers\PromptServiceProvider;
use Src\PullRequest\Providers\PullRequestServiceProvider;
use Src\Rule\Providers\RuleServiceProvider;
use Src\Session\Providers\SessionServiceProvider;
use Src\Setting\Providers\SettingServiceProvider;
use Src\Skill\Providers\SkillServiceProvider;
use Src\Ticket\Providers\TicketServiceProvider;

return [
	AppServiceProvider::class,
	BoardServiceProvider::class,
	FetchServiceProvider::class,
	ProjectServiceProvider::class,
	PromptServiceProvider::class,
	PullRequestServiceProvider::class,
	RuleServiceProvider::class,
	SessionServiceProvider::class,
	SettingServiceProvider::class,
	SkillServiceProvider::class,
	TicketServiceProvider::class,
];
