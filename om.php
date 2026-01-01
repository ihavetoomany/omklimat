<?php
/**
 * About page - information about Lars Werner
 */
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Om författaren - Om klimat</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=3">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-title-wrapper">
                <h1>Om klimat</h1>
                <a href="admin/login.php" class="admin-sun-button" aria-label="Gå till admin-sidan">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="5"></circle>
                        <line x1="12" y1="1" x2="12" y2="3"></line>
                        <line x1="12" y1="21" x2="12" y2="23"></line>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                        <line x1="1" y1="12" x2="3" y2="12"></line>
                        <line x1="21" y1="12" x2="23" y2="12"></line>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                    </svg>
                </a>
            </div>
        </header>
        
        <main>
            <article class="single-post">
                <h2>Om författaren</h2>
                
                <div class="post-content">
                    <p><strong>Lite om min bakgrund.</strong></p>
                    
                    <p>Jag utbildade mig till meteorolog i Stockholm 1969. Parallellt med studierna på SMHI under nästan 4 år skrev jag in mig på Stockholms universitet och tog där examen i meteorologi. Det sista jag gjorde på universitetet var en muntlig tentamen för professor Bert Bolin för två betyg i meteorologi. Bert Bolin är mest känd som den första ordföranden för IPCC (FN:s klimatpanel). Sedan fyllde jag på till en fil.kand. i naturvetenskapliga ämnen på universitetet i Lund (40 poäng i meteorologi, 40 poäng i mattematik, 40 poäng i miljövård och 20 poäng teoretisk fysik) 1974.</p>
                    
                    <p>Redan 1969 började jag arbeta som flygmeteorolog på Bulltofta flygplats i Malmö och när Sturups flygplats blev färdig 1972 blev det 25 år där (en tid med många nattvakter och slitsamt jobb). SMHI Malmö flyttade nämligen in till Öresundshuset i Malmö hamn 1997 och där jobbade jag med främst prognoser för lantbruk och radio. Vi sände direkt 2-3 gånger per dag i nästan alla lokalradiostationer i Götaland. Morgonvakten började kl 04.30 och det blev under morgonen och f.m. ett 20-tal intervjuer i radio Malmöhus, Kristianstad, Halland, Blekinge, Kronoberg, Kalmar, Göteborg, Jönköping, Gotland osv. Det gällde att vårda skånskan eftersom vi även drog vädret i både TV och andra radioprogram (ring så spelar vi bl. a.). Ofta blev det också väderinslag i Sydnytt, särskilt med väderreporter Pelle Helmersson.</p>
                    
                    <p>Under tiden i Malmö blev jag klimatansvarig och mitt stora intresse har sedan dess varit framtidens klimat. Under mer än 20 år har jag dessutom skrivit Månadens väder i Sydsvenskan, HD och några andra skånska tidningar. När jag sammanställde dessa artiklar blev det allt tydligare att vi gick mot varmare tider. Det märkte jag också på ett annat område. Jag var under många år ansvarig för väderintygen på SMHI. Tvister vad gäller åskväder, stormar, översvämningar blev allt fler och då kunde man beställa ett väderintyg av SMHI. Jag skrev nästan alla väderintyg och mycket ofta hamnade jag som vittne i olika domstolar i landet. I Stockholm känner jag väl till både Tingsrätten och Hovrätten.</p>
                    
                    <p>År 2005 höll jag mitt första föredrag om framtidens klimat och sedan dess har det blivit åtskilliga föredrag (mer än 200 st), mest i Skåne, men även längre norrut. Väderprogrammet Cirrus i P1 var jag med om den första sändningen både i studio och utomhus. Vi sände nämligen en söndagmorgon sommaren 2009 direkt ifrån taket på Torson (finns på you tube - sök cirrus+torson).</p>
                    
                    <p>Efter pensionen (2009-2010) har jag fortsatt att medverka i Cirrus, men mitt största intresse är nu den förstärkta växthuseffekten. För första gången i historien påverkar vi människor klimatet med våra utsläpp - vi är därmed huvudaktörer i ett gigantiskt experiment med klimatet, som sannerligen är ett högriskprojekt!! Om detta håller jag föredrag och har också skrivit en bok: En handbok för dig som vill göra skillnad - Klimatsmart, som kan beställas på kommunlitteraturs hemsida.</p>
                </div>
                
                <div class="post-footer">
                    <a href="index.php" class="back-link">← Tillbaka till alla inlägg</a>
                </div>
            </article>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> Blogg</p>
        </footer>
    </div>
</body>
</html>

