<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Page Not Found — Richiamo Coffee</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root{--espresso:#1C0A00;--caramel:#C68642;--latte:#D4A96A;--cream:#F5E6C8;}
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',sans-serif;background:var(--espresso);min-height:100vh;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;}
    body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse at 30% 50%,rgba(198,134,66,.08) 0%,transparent 50%);pointer-events:none;}
    .box{text-align:center;padding:2rem;position:relative;z-index:1;animation:fadeUp .5s ease both;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    .cup{font-size:5rem;margin-bottom:1rem;display:block;animation:wobble 2s ease-in-out infinite;}
    @keyframes wobble{0%,100%{transform:rotate(-3deg)}50%{transform:rotate(3deg)}}
    .code{font-family:'Playfair Display',serif;font-size:6rem;color:var(--caramel);line-height:1;margin-bottom:.5rem;}
    h1{font-family:'Playfair Display',serif;font-size:1.5rem;color:var(--cream);margin-bottom:.75rem;}
    p{color:var(--latte);font-size:.9rem;margin-bottom:2rem;line-height:1.7;max-width:360px;}
    .actions{display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;}
    .btn-primary{background:var(--caramel);color:var(--espresso);border:none;border-radius:2rem;padding:.75rem 1.75rem;font-size:.9rem;font-weight:600;text-decoration:none;transition:all .2s;}
    .btn-primary:hover{background:var(--latte);}
    .btn-secondary{background:transparent;color:var(--cream);border:1.5px solid rgba(255,255,255,.25);border-radius:2rem;padding:.7rem 1.5rem;font-size:.875rem;text-decoration:none;transition:all .2s;}
    .btn-secondary:hover{border-color:var(--cream);}
  </style>
</head>
<body>
  <div class="box">
    <span class="cup">☕</span>
    <div class="code">404</div>
    <h1>Page not found</h1>
    <p>Looks like this page went cold. It may have been moved, deleted, or never existed in the first place.</p>
    <div class="actions">
      <a href="/richiamo/" class="btn-primary">Back to home</a>
      <a href="javascript:history.back()" class="btn-secondary">← Go back</a>
    </div>
  </div>
</body>
</html>
