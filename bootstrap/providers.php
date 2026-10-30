<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Src\Board\Providers\BoardServiceProvider;
use Src\Fetch\Providers\FetchServiceProvider;
use Src\Project\Providers\ProjectServiceProvider;
use Src\Prompt\Providers\PromptServiceProvider;
use Src\PullRequest\Providers\PullRequestServiceProvider;
use Src\Review\Providers\ReviewServiceProvider;
use Src\Rule\Providers\RuleServiceProvider;
use Src\Session\Providers\SessionServiceProvider;
use Src\Setting\Providers\SettingServiceProvider;
use Src\Skill\Providers\SkillServiceProvider;
use Src\Task\Providers\TaskServiceProvider;
use Src\Ticket\Providers\TicketServiceProvider;
use Src\Worktree\Providers\WorktreeServiceProvider;

return [
	AppServiceProvider::class,
	BoardServiceProvider::class,
	FetchServiceProvider::class,
	ProjectServiceProvider::class,
	PromptServiceProvider::class,
	PullRequestServiceProvider::class,
	ReviewServiceProvider::class,
	RuleServiceProvider::class,
	SessionServiceProvider::class,
	SettingServiceProvider::class,
	SkillServiceProvider::class,
	TaskServiceProvider::class,
	TicketServiceProvider::class,
	WorktreeServiceProvider::class,
];
