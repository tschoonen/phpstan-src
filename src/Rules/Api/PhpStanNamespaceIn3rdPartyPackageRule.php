<?php declare(strict_types = 1);

namespace PHPStan\Rules\Api;

use Nette\Utils\Json;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\File\FileReader;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Stmt\Namespace_>
 */
class PhpStanNamespaceIn3rdPartyPackageRule implements Rule
{

	private ApiRuleHelper $apiRuleHelper;

	public function __construct(ApiRuleHelper $apiRuleHelper)
	{
		$this->apiRuleHelper = $apiRuleHelper;
	}

	public function getNodeType(): string
	{
		return Node\Stmt\Namespace_::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$namespace = null;
		if ($node->name !== null) {
			$namespace = $node->name->toString();
		}
		if (!$this->apiRuleHelper->isCalledFromPhpStan($namespace)) {
			return [];
		}

		$composerJson = $this->findComposerJsonContents(dirname($scope->getFile()));
		if ($composerJson === null) {
			return [];
		}

		$packageName = $composerJson['name'] ?? null;
		if ($packageName !== null && strpos($packageName, 'phpstan/') === 0) {
			return [];
		}

		return [
			RuleErrorBuilder::message('Declaring PHPStan namespace is not allowed in 3rd party packages.')
				->tip("See:\n   https://phpstan.org/developing-extensions/backward-compatibility-promise")
				->build(),
		];
	}

	/**
	 * @return mixed[]|null
	 */
	private function findComposerJsonContents(string $fromDirectory): ?array
	{
		if (!is_dir($fromDirectory)) {
			return null;
		}

		$composerJsonPath = $fromDirectory . '/composer.json';
		if (!is_file($composerJsonPath)) {
			return $this->findComposerJsonContents(dirname($fromDirectory));
		}

		try {
			return Json::decode(FileReader::read($composerJsonPath), Json::FORCE_ARRAY);
		} catch (\Nette\Utils\JsonException $e) {
			return null;
		} catch (\PHPStan\File\CouldNotReadFileException $e) {
			return null;
		}
	}

}
