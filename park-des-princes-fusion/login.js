const signUpBtn = document.getElementById('signUpBtn'); // Bouton li kay-biyen Créer Compte
const signInBtn = document.getElementById('signInBtn'); // Bouton li kay-biyen Login
const mainContainer = document.getElementById('mainContainer'); // L-Cadr l-kbir

// 2. L'evenement dyal l-click 3la bouton 'signUpBtn'
signUpBtn.addEventListener('click', () => {
    // k-n-zidou class li k-t-dir l-animation l-jiha d l-isser
    mainContainer.classList.add("right-panel-active");
});

// 3. L'evenement dyal l-click 3la bouton 'signInBtn'
signInBtn.addEventListener('click', () => {
    // k-n-7iydou l-class bach kolshi y-rje3 l-blas-to l-asliya
    mainContainer.classList.remove("right-panel-active");
});