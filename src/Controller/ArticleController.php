<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/article')]
#[IsGranted('ROLE_USER')]
final class ArticleController extends AbstractController
{
    #[Route(name: 'app_article_index', methods: ['GET'])]
    public function index(ArticleRepository $articleRepository): Response
    {
        return $this->render('article/index.html.twig', [
            'articles' => $articleRepository->findAll(),
        ]);
    }


    #[IsGranted('ROLE_USER')]
    #[Route('/new', name: 'app_article_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $article = new Article();
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Récupération du fichier depuis le formulaire
            $imageFile = $form->get('image')->getData();
            // Si un fichier a été envoyé :
            if ($imageFile) {
                // On travail le nom du fichier
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                // nécessaire pour inclure le nom du fichier à une partie de l'URL de manière sécurisée
                $safeFilename = $slugger->slug($originalFilename);
                // On ajoute un identifiant unique au nom du fichier pour s'assurer que deux fichiers n'aient pas le même nom
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                // On déplace le fichier sur le serveur
                $imageFile->move(
                    $this->getParameter('images_directory'),
                    $newFilename
                );
                // On ajoute à notre objet article
                $article->setImage($newFilename);
            }

            $entityManager->persist($article);
            $entityManager->flush();

            return $this->redirectToRoute('app_article_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('article/new.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }



    #[Route('/{id}', name: 'app_article_show', methods: ['GET'])]
    public function show(Article $article): Response
    {
        return $this->render('article/show.html.twig', [
            'article' => $article,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/edit', name: 'app_article_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Article $article, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Récupération du fichier depuis le formulaire
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {

                // 🔥 SUPPRESSION DE L’ANCIENNE IMAGE
                if ($article->getImage()) {
                    $oldImagePath = $this->getParameter('images_directory') . '/' . $article->getImage();
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                // 🔥 UPLOAD DE LA NOUVELLE IMAGE
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                $imageFile->move(
                    $this->getParameter('images_directory'),
                    $newFilename
                );

                $article->setImage($newFilename);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_article_index');
        }

        return $this->render('article/edit.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}', name: 'app_article_delete', methods: ['POST'])]
    public function delete(Request $request, Article $article, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $article->getId(), $request->getPayload()->getString('_token'))) {

            // 🔥 SUPPRESSION DU FICHIER IMAGE
            if ($article->getImage()) {
                $imagePath = $this->getParameter('images_directory') . '/' . $article->getImage();

                if (file_exists($imagePath)) {
                    unlink($imagePath); // supprime le fichier du serveur
                }
            }

            // 🔥 SUPPRESSION DE L’ARTICLE EN BASE
            $entityManager->remove($article);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_article_index', [], Response::HTTP_SEE_OTHER);
    }
}
