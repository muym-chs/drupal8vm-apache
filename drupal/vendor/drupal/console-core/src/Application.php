<?php

namespace Drupal\Console\Core;

use Drupal\Console\Core\EventSubscriber\ShowGenerateCountCodeLinesListener;
use Drupal\Console\Core\Utils\TranslatorManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Application as BaseApplication;
use Drupal\Console\Core\EventSubscriber\DefaultValueEventListener;
use Drupal\Console\Core\EventSubscriber\ShowGenerateChainListener;
use Drupal\Console\Core\EventSubscriber\ShowTipsListener;
use Drupal\Console\Core\EventSubscriber\ShowWelcomeMessageListener;
use Drupal\Console\Core\EventSubscriber\ValidateExecutionListener;
use Drupal\Console\Core\EventSubscriber\ShowGeneratedFilesListener;
use Drupal\Console\Core\EventSubscriber\ShowGenerateInlineListener;
use Drupal\Console\Core\EventSubscriber\CallCommandListener;
use Drupal\Console\Core\Utils\ConfigurationManager;
use Drupal\Console\Core\Style\DrupalStyle;
use Drupal\Console\Core\Utils\ChainDiscovery;
use Drupal\Console\Core\Command\Chain\ChainCustomCommand;

/**
 * Class Application
 *
 * @package Drupal\Console
 */
class Application extends BaseApplication
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var string
     */
    protected $commandName;

    /**
     * ConsoleApplication constructor.
     *
     * @param ContainerInterface $container
     * @param string             $name
     * @param string             $version
     */
    public function __construct(
        ContainerInterface$container,
        $name,
        $version
    ) {
        $this->container = $container;
        parent::__construct($name, $version);
        $this->addOptions();
    }

    /**
     * @return TranslatorManagerInterface
     */
    public function getTranslator()
    {
        if ($this->container) {
            return $this->container->get('console.translator_manager');
        }

        return null;
    }

    /**
     * @param $key string
     *
     * @return string
     */
    public function trans($key)
    {
        if ($this->getTranslator()) {
            return $this->getTranslator()->trans($key);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $io = new DrupalStyle($input, $output);
        $messages = [];
        $commandName = $this->getCommandName($input)?:'list';
        $this->commandName = $commandName;
        $this->registerEvents();
        $this->registerExtendCommands();

        /**
         * @var ConfigurationManager $configurationManager
         */
        $configurationManager = $this->container
            ->get('console.configuration_manager');

        $config = $configurationManager->getConfiguration()
            ->get('application.extras.config')?:'true';
        if ($config === 'true') {
            $this->registerCommandsFromAutoWireConfiguration();
        }

        $chains = $configurationManager->getConfiguration()
            ->get('application.extras.chains')?:'true';
        if ($chains === 'true') {
            $this->registerChainCommands();
        }


        if ($commandName && !$this->has($commandName)) {
            $isValidCommand = false;
            $config = $configurationManager->getConfiguration();
            $mappings = $config
                ->get('application.commands.mappings');

            if (array_key_exists($commandName, $mappings)) {
                $commandNameMap = $mappings[$commandName];
                $messages['warning'][] = sprintf(
                    $this->trans('application.errors.renamed-command'),
                    $commandName,
                    $commandNameMap
                );
                $this->add(
                    $this->find($commandNameMap)->setAliases([$commandName])
                );
                $isValidCommand = true;
            }

            $drushCommand = $configurationManager->readDrushEquivalents($commandName);
            if ($drushCommand) {
                $this->add(
                    $this->find($drushCommand)->setAliases([$commandName])
                );
                $isValidCommand = true;
                $messages['warning'][] = sprintf(
                    $this->trans('application.errors.drush-command'),
                    $commandName,
                    $drushCommand
                );
            }

            if (!$isValidCommand) {
                $io->error(
                    sprintf(
                        $this->trans('application.errors.invalid-command'),
                        $this->commandName
                    )
                );

                return 1;
            }
        }

        $code = parent::doRun(
            $input,
            $output
        );

        if ($this->commandName != 'init' && $configurationManager->getMissingConfigurationFiles(
        )
        ) {
            $io->warning(
                $this->trans('application.site.errors.missing-config-file')
            );
            $io->listing($configurationManager->getMissingConfigurationFiles());
            $io->commentBlock(
                $this->trans(
                    'application.site.errors.missing-config-file-command'
                )
            );
        }

        if ($this->getCommandName(
            $input
        ) == 'list' && $this->container->hasParameter('console.warning')
        ) {
            $io->warning(
                $this->trans($this->container->getParameter('console.warning'))
            );
        }

        foreach ($messages as $type => $message) {
            $io->$type($message);
        }

        return $code;
    }

    /**
     * registerEvents
     */
    private function registerEvents()
    {
        $dispatcher = new EventDispatcher();
        /* @todo Register listeners as services */
        $dispatcher->addSubscriber(
            new ValidateExecutionListener(
                $this->container->get('console.translator_manager'),
                $this->container->get('console.configuration_manager')
            )
        );
        $dispatcher->addSubscriber(
            new ShowWelcomeMessageListener(
                $this->container->get('console.translator_manager')
            )
        );
        $dispatcher->addSubscriber(
            new DefaultValueEventListener(
                $this->container->get('console.configuration_manager')
            )
        );
        $dispatcher->addSubscriber(
            new ShowTipsListener(
                $this->container->get('console.translator_manager')
            )
        );
        $dispatcher->addSubscriber(
            new CallCommandListener(
                $this->container->get('console.chain_queue')
            )
        );
        $dispatcher->addSubscriber(
            new ShowGeneratedFilesListener(
                $this->container->get('console.file_queue'),
                $this->container->get('console.show_file')
            )
        );
        $dispatcher->addSubscriber(
            new ShowGenerateInlineListener(
                $this->container->get('console.translator_manager')
            )
        );
        $dispatcher->addSubscriber(
            new ShowGenerateChainListener(
                $this->container->get('console.translator_manager')
            )
        );

        $dispatcher->addSubscriber(
            new ShowGenerateCountCodeLinesListener(
                $this->container->get('console.translator_manager'),
                $this->container->get('console.count_code_lines')
            )
        );

        $this->setDispatcher($dispatcher);
    }

    /**
     * addOptions
     */
    private function addOptions()
    {
        // Get the configuration from config.yml.
        $env = $this->container
          ->get('console.configuration_manager')
          ->getConfiguration()
          ->get('application.environment');

        $this->getDefinition()->addOption(
            new InputOption(
                '--env',
                '-e',
                InputOption::VALUE_OPTIONAL,
                $this->trans('application.options.env'),
                !empty($env) ? $env : 'prod'
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--root',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('application.options.root')
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--debug',
                null,
                InputOption::VALUE_NONE,
                $this->trans('application.options.debug')
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--learning',
                null,
                InputOption::VALUE_NONE,
                $this->trans('application.options.learning')
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--generate-chain',
                '-c',
                InputOption::VALUE_NONE,
                $this->trans('application.options.generate-chain')
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--generate-inline',
                '-i',
                InputOption::VALUE_NONE,
                $this->trans('application.options.generate-inline')
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--generate-doc',
                '-d',
                InputOption::VALUE_NONE,
                $this->trans('application.options.generate-doc')
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--target',
                '-t',
                InputOption::VALUE_OPTIONAL,
                $this->trans('application.options.target')
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--uri',
                '-l',
                InputOption::VALUE_REQUIRED,
                $this->trans('application.options.uri')
            )
        );
        $this->getDefinition()->addOption(
            new InputOption(
                '--yes',
                '-y',
                InputOption::VALUE_NONE,
                $this->trans('application.options.yes')
            )
        );
    }

    /**
     * registerExtendCommands
     */
    private function registerExtendCommands()
    {
        $this->container->get('console.configuration_manager')
            ->loadExtendConfiguration();
    }

    /**
     * registerCommandsFromAutoWireConfiguration
     */
    private function registerCommandsFromAutoWireConfiguration()
    {
        $configuration = $this->container->get('console.configuration_manager')
            ->getConfiguration();

        $autoWireForcedCommands = $configuration
            ->get('application.autowire.commands.forced');

        if (!is_array($autoWireForcedCommands)) {
            return;
        }

        foreach ($autoWireForcedCommands as $autoWireForcedCommand) {
            try {
                if (!$autoWireForcedCommand['class']) {
                    continue;
                }

                $reflectionClass = new \ReflectionClass(
                    $autoWireForcedCommand['class']
                );

                $arguments = [];
                if (array_key_exists('arguments', $autoWireForcedCommand)) {
                    foreach ($autoWireForcedCommand['arguments'] as $argument) {
                        $argument = substr($argument, 1);
                        $arguments[] = $this->container->get($argument);
                    }
                }

                $command = $reflectionClass->newInstanceArgs($arguments);

                if (method_exists($command, 'setTranslator')) {
                    $command->setTranslator(
                        $this->container->get('console.translator_manager')
                    );
                }
                if (method_exists($command, 'setContainer')) {
                    $command->setContainer(
                        $this->container->get('service_container')
                    );
                }

                $this->add($command);
            } catch (\Exception $e) {
                echo $e->getMessage() . PHP_EOL;
                continue;
            }
        }

        $autoWireNameCommand = $configuration->get(
            sprintf(
                'application.autowire.commands.name.%s',
                $this->commandName
            )
        );

        if ($autoWireNameCommand) {
            try {
                $arguments = [];
                if (array_key_exists('arguments', $autoWireNameCommand)) {
                    foreach ($autoWireNameCommand['arguments'] as $argument) {
                        $argument = substr($argument, 1);
                        $arguments[] = $this->container->get($argument);
                    }
                }

                $reflectionClass = new \ReflectionClass(
                    $autoWireNameCommand['class']
                );
                $command = $reflectionClass->newInstanceArgs($arguments);

                if (method_exists($command, 'setTranslator')) {
                    $command->setTranslator(
                        $this->container->get('console.translator_manager')
                    );
                }
                if (method_exists($command, 'setContainer')) {
                    $command->setContainer(
                        $this->container->get('service_container')
                    );
                }

                $this->add($command);
            } catch (\Exception $e) {
                echo $e->getMessage() . PHP_EOL;
            }
        }
    }

    /**
     * registerChainCommands
     */
    public function registerChainCommands()
    {
        /**
         * @var ChainDiscovery $chainDiscovery
         */
        $chainDiscovery = $this->container->get('console.chain_discovery');
        $chainCommands = $chainDiscovery->getChainCommands();

        foreach ($chainCommands as $name => $chainCommand) {
            try {
                $file = $chainCommand['file'];
                $description = $chainCommand['description'];
                $placeHolders = $chainCommand['placeholders'];
                $command = new ChainCustomCommand(
                    $name,
                    $description,
                    $placeHolders,
                    $file
                );
                $this->add($command);
            } catch (\Exception $e) {
                echo $e->getMessage() . PHP_EOL;
            }
        }
    }

    /**
     * Finds a command by name or alias.
     *
     * @param string $name A command name or a command alias
     *
     * @return mixed A Command instance
     *
     * Override parent find method to avoid name collisions with automatically
     * generated command abbreviations.
     * Command name validation was previously done at doRun method.
     */
    public function find($name)
    {
        return $this->get($name);
    }
}
