<?php

namespace App\Controller\Admin;

use App\Entity\Archiver;
use App\Repository\ArchiverRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatableMessage;

class PdfController extends AbstractController
{
    /**
     * @Route("/admin/pdf/combine", name="admin_pdf_combine", methods={"GET"})
     */
    public function combine(Request $request): Response
    {
        $form = $this->createCombineForm();

        return $this->renderForm('admin/pdf/combine.html.twig', ['form' => $form]);
    }

    /**
     * @Route("/admin/pdf/combine", name="admin_pdf_combine_run", methods={"POST"})
     */
    public function combineRun(Request $request, Packages $packages, ParameterBagInterface $parameters, ArchiverRepository $archiverRepository): Response
    {
        $form = $this->createCombineForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new BadRequestHttpException();
        }

        $data = $form->getData();
        $hearingId = $data['hearing_id'] ?? null;
        $archiver = $data['archiver'];

        $response = new StreamedResponse();
        // disables FastCGI buffering in nginx only for this response
        $response->headers->set('X-Accel-Buffering', 'no');

        $action = 'run';
        $verbosity = '-vvv';

        $cmd = [
            $parameters->get('kernel.project_dir').'/bin/console',
            'app:pdf:combine',
            $archiver->getId()->toRfc4122(),
            $action,
            $hearingId,
            $verbosity,
        ];

        // Start a process running the command and continously send output to the browser.
        // @see https://www.php.net/manual/en/function.ob-implicit-flush.php#116748
        ob_implicit_flush();

        // We need to be able to run for a long time.
        set_time_limit(0);

        $callback = function () use ($cmd, $packages) {
            echo '<html>';
            echo '<head>';
            printf('<link rel="stylesheet" href="%s"/>', $packages->getUrl('build/console.css'));
            echo '</head>';
            echo '<body>';
            printf('<pre><code>');
            $process = new Process($cmd);
            // Allow the process to run for at most 10 minutes.
            $process->setTimeout(10 * 60);
            // https://symfony.com/doc/current/components/process.html#getting-real-time-process-output
            $process->run(function ($type, $buffer) {
                $classNames = [Process::ERR === $type ? 'stderr' : 'stdout'];
                if (preg_match('/^\[(?<type>debug|info)\]/', $buffer, $matches)) {
                    $classNames[] = $matches['type'];
                }
                printf('<div class="%s">%s</div>', implode(' ', $classNames), htmlspecialchars($buffer));
                ob_flush();
            });
            printf('</code></pre>');
            printf('<script>try { parent.processCompleted(%s) } catch (ex) { console.debug(ex) }</script>',
                json_encode([
                    'exit_code' => $process->getExitCode(),
                    'exit_code_text' => $process->getExitCodeText(),
                ]));
            echo '</body>';
            echo '</html>';
        };

        $response->setCallback($callback);

        return $response;
    }

    private function createCombineForm()
    {
        return $this->createFormBuilder()
            ->add('hearing_id', TextType::class, [
                'label' => new TranslatableMessage('Hearing id'),
            ])
            ->add('archiver', EntityType::class, [
                'class' => Archiver::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('a')
                        ->where('a.type = :type')
                        ->setParameter('type', Archiver::TYPE_PDF_COMBINE)
                        ->andWhere('a.enabled = :enabled')
                        ->setParameter('enabled', true)
                        ->orderBy('a.name', 'ASC');
                },
                'label' => new TranslatableMessage('Archiver'),
            ])
            ->add('run', SubmitType::class, [
                'label' => new TranslatableMessage('Combine'),
            ])
            ->setAction($this->generateUrl('admin_pdf_combine_run'))
            ->getForm();
    }
}
