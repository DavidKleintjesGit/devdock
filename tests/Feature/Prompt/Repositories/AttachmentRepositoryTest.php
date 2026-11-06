<?php

declare(strict_types=1);

namespace Tests\Feature\Prompt\Repositories;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Src\Prompt\Contracts\AttachmentRepositoryContract;
use Src\Prompt\Contracts\PromptRunRepositoryContract;
use Src\Prompt\Models\PromptRun;
use Src\Prompt\Objects\AttachmentObject;
use Src\Prompt\Objects\PromptRunEntity;
use Src\Prompt\Objects\PromptRunId;
use Src\Prompt\Repositories\AttachmentRepository;
use Tests\TestCase;

/**
 * @see AttachmentRepository
 */
class AttachmentRepositoryTest extends TestCase
{
	private string $card;

	protected function setUp(): void
	{
		parent::setUp();

		$this->card = 'todo:' . fake()->unique()->numberBetween(100000, 999999);
	}

	protected function tearDown(): void
	{
		resolve(AttachmentRepositoryContract::class)->deleteFor($this->card);

		parent::tearDown();
	}

	#[Test]
	public function store_chunk_puts_the_pieces_together_and_attach_moves_the_file_to_the_card(): void
	{
		$attachmentRepository = resolve(AttachmentRepositoryContract::class);
		$upload = bin2hex(random_bytes(8));

		$first = $attachmentRepository->storeChunk($upload, 0, 2, 'my log.txt', 'first half, ');
		$whole = $attachmentRepository->storeChunk($upload, 1, 2, 'my log.txt', 'second half');
		$paths = $attachmentRepository->attach($this->card, [$upload]);

		$this->assertNull($first);
		$this->assertSame('my-log.txt', $whole?->name);
		$this->assertCount(1, $paths);
		$this->assertSame('first half, second half', file_get_contents($paths[0]));
		$this->assertNull($attachmentRepository->getPending($upload));
		$this->assertSame($attachmentRepository->folderFor($this->card), dirname($paths[0]));
	}

	#[Test]
	public function store_chunk_refuses_a_piece_whose_beginning_never_arrived(): void
	{
		$this->expectException(RuntimeException::class);

		resolve(AttachmentRepositoryContract::class)->storeChunk(bin2hex(random_bytes(8)), 1, 2, 'notes.txt', 'second half');
	}

	#[Test]
	public function file_at_refuses_a_path_outside_the_attachments_folder(): void
	{
		$file = resolve(AttachmentRepositoryContract::class)->fileAt('../../.env');

		$this->assertNull($file);
	}

	#[Test]
	public function a_message_lists_its_attachments_apart_from_the_text(): void
	{
		$attachmentRepository = resolve(AttachmentRepositoryContract::class);
		$upload = bin2hex(random_bytes(8));
		$attachmentRepository->storeChunk($upload, 0, 1, 'screen.png', 'png');
		$paths = $attachmentRepository->attach($this->card, [$upload]);
		$promptRun = PromptRun::factory()->create(['card' => $this->card, 'prompt' => PromptRunEntity::withAttachments('What do you see?', $paths)]);

		$promptRunEntity = resolve(PromptRunRepositoryContract::class)->getById(new PromptRunId(id: $promptRun->id));

		$this->assertSame('What do you see?', $promptRunEntity?->message());
		$this->assertSame(['screen.png'], array_map(fn (AttachmentObject $attachmentObject): string => $attachmentObject->name, $promptRunEntity->attachments()));
		$this->assertTrue($promptRunEntity->attachments()[0]->isImage());
	}
}
