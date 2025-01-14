<?php

namespace App\Controller\Admin;

use App\Command\Pdf\CombineCommand;
use App\Entity\Archiver;
use App\Kernel;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatableMessage;

class PdfController extends AbstractController
{
    public function __construct(
        private readonly Kernel $kernel,
        private readonly Packages $packages,
    ) {
    }

    #[Route(path: '/admin/pdf/combine', name: 'admin_pdf_combine', methods: ['GET'])]
    public function combine(Request $request): Response
    {
        return $this->render('admin/pdf/combine.html.twig', ['form' => $this->createCombineForm()]);
    }

    #[Route(path: '/admin/pdf/combine', name: 'admin_pdf_combine_run', methods: ['POST'])]
    public function combineRun(Request $request, ParameterBagInterface $parameters): Response
    {
        $form = $this->createCombineForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new BadRequestHttpException();
        }

        $data = $form->getData();
        $hearingId = $data['hearing_id'] ?? null;
        $archiver = $data['archiver'];
        $action = $data['action'] ?? 'run';
        $verbosity = '-vvv';

        $commandName = (new \ReflectionClass(CombineCommand::class))
            ->getAttributes(AsCommand::class)[0]->getArguments()['name'];

        $cmd = [
            $parameters->get('kernel.project_dir').'/bin/console',
            $commandName,
            $archiver->getId()->toRfc4122(),
            $action,
            $hearingId,
            $verbosity,
        ];

        $input = new ArrayInput([
            'command' => $commandName,
            'archiver' => $archiver->getId()->toRfc4122(),
            'action' => $action,
            'hearing' => $hearingId,
            $verbosity => true,
        ]);

        return $this->runCommand($input);
    }

    private function runCommand(InputInterface $input): Response
    {
        // https://symfony.com/doc/current/console/command_in_controller.html
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $response = new StreamedResponse();
        // disables FastCGI buffering in nginx only for this response
        // https://stackoverflow.com/questions/61029079/how-to-turn-off-buffering-on-nginx-server-for-server-sent-event
        $response->headers->set('X-Accel-Buffering', 'no');

        // Start a process running the command and continuously send output to the browser.
        // @see https://www.php.net/manual/en/function.ob-implicit-flush.php#116748
        ob_implicit_flush();

        // We need to be able to run for a long time.
        set_time_limit(0);

        $callback = function () use ($application, $input): void {
            echo '<html>';
            echo '<head>';
            printf('<link rel="stylesheet" href="%s"/>', $this->packages->getUrl('build/console.css'));
            echo '</head>';
            echo '<body>';
            printf('<pre><code>');

            printf(str_repeat('-', 120).PHP_EOL);
            printf('> '.$input.PHP_EOL);
            printf(str_repeat('-', 120).PHP_EOL);

            $exitCode = $application->run($input, new class extends Output {
                protected function doWrite(string $message, bool $newline): void
                {
                    echo $message;
                    if ($newline) {
                        echo PHP_EOL;
                    }
                    ob_flush();
                }
            });

            printf('</code></pre>');
            printf('<script>try { parent.processCompleted(%s) } catch (ex) { console.debug(ex) }</script>',
                json_encode([
                    'exit_code' => $exitCode,
                ]));
            echo '</body>';
            echo '</html>';
        };

        $response->setCallback($callback);

        return $response;
    }

    private function createCombineForm()
    {
        $actions = array_merge(['run'], CombineCommand::ACTIONS);

        return $this->createFormBuilder()
            ->add('hearing_id', TextType::class, [
                'label' => new TranslatableMessage('Hearing id'),
            ])
            ->add('archiver', EntityType::class, [
                'class' => Archiver::class,
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('a')
                    ->where('a.type = :type')
                    ->setParameter('type', Archiver::TYPE_PDF_COMBINE)
                    ->andWhere('a.enabled = :enabled')
                    ->setParameter('enabled', true)
                    ->orderBy('a.name', 'ASC'),
                'label' => new TranslatableMessage('Archiver'),
            ])
            ->add('action', ChoiceType::class, [
                'choices' => array_combine($actions, $actions),
                'placeholder' => '',
            ])
            ->add('start', SubmitType::class, [
                'label' => new TranslatableMessage('Start'),
            ])
            ->setAction($this->generateUrl('admin_pdf_combine_run'))
            ->getForm();
    }
}
