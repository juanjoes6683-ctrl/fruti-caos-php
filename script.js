// ======== DATOS PRINCIPALES ========
const FRUTAS_DATA = [
  { nivel: 1, emoji: "🍒", radio: 20, puntaje: 10 }, 
  { nivel: 2, emoji: "🍓", radio: 24, puntaje: 20 },
  { nivel: 3, emoji: "🍊", radio: 28, puntaje: 30 },
  { nivel: 4, emoji: "🍑", radio: 32, puntaje: 40 },
  { nivel: 5, emoji: "🍋", radio: 36, puntaje: 40 },
  { nivel: 6, emoji: "🥭", radio: 40, puntaje: 60 },
  { nivel: 7, emoji: "🍎", radio: 44, puntaje: 70 },
  { nivel: 8, emoji: "🍐", radio: 48, puntaje: 80 },
  { nivel: 9, emoji: "🍍", radio: 52, puntaje: 90 },
  { nivel: 10, emoji: "🥝", radio: 56, puntaje: 100 },
  { nivel: 11, emoji: "🥥", radio: 60, puntaje: 110 },
  { nivel: 12, emoji: "🍉", radio: 64, puntaje: 5000 } 
];
const MAX_ITERACIONES_COLISIONES = 15; 
const PUNTAJE_MAXIMO = 15000;

// URL base de la API REST (backend PHP). Es relativa a index.html (carpeta "api" del mismo proyecto).
// Si el backend estuviera en otro servidor, se cambia por la URL completa, ej: "http://localhost:8000/api"
const API_URL = "api";

const gravedad = 0.3; 
const friccion = 0.992; 
const intervaloFisica = 1000 / 60; 
const minVelocidad = 0.5; 
const coeficienteRestitucion = 0.01; 

let siguienteId = 1;

const LIMITE_SUPERIOR_GAME_OVER = 0; 

/**
 * Clase que representa una fruta individual dentro de la jarra.
 */
class Fruta {
  constructor(nivel, x, y) {
    const data = FRUTAS_DATA[nivel - 1];
    this.id = siguienteId++
    this.nivel = nivel;
    this.radio = data.radio;
    this.emoji = data.emoji;
    this.puntaje = data.puntaje;
    this.x = x;
    this.y = y;
    this.vx = 0;
    this.vy = 0;
    this.fusionando = false;
    this.haSidoSoltada = false; 
    this.elemento = this.crearElementoDOM();
    this.volumen = Math.pow(this.radio, 3);     //crea una "ilusion" de efecto 3D (propiedades de volumen para la fruta)
  }

  crearElementoDOM() {
    const frutaElemento = document.createElement("div");    //crea el elemento "fruta"
    frutaElemento.className = "jar-fruit";
    frutaElemento.textContent = this.emoji;                 //muestra la fruta(emoji de fruta)
    frutaElemento.style.width = `${this.radio * 2}px`;      //crea el circulo donde estará la fruta
    frutaElemento.style.height = `${this.radio * 2}px`;
    frutaElemento.style.fontSize = `${this.radio * 1.2}px`;       //hace que el emoji sea de un tamaño adecuado para su circulo total
    return frutaElemento;
  }

  actualizarPosicionVisual() {
    this.elemento.style.left = `${this.x - this.radio}px`;
    this.elemento.style.top = `${this.y - this.radio}px`;
  }
}

/**
 * Clase que gestiona la lógica de las frutas en la bandeja (las que se sueltan).
 */
class Bandeja {
  constructor(elementoBandeja, juego) {     //creando elementos del juego
    this.elemento = elementoBandeja;
    this.juego = juego; 
    this.inicializarEventos();
  }

  agregarFrutaADrag(nivel) {
    const frutaData = FRUTAS_DATA[nivel - 1];   //busca informacion de la fruta
    let div = document.createElement("div");
    div.className = "fruit-draggable";      //nombre del elemento
    div.draggable = true;                   //activa la funcion para que pueda agarrarse
    div.dataset.nivel = nivel;              //guarda el nivel de la fruta para recuperarla al soltarla
    div.textContent = frutaData.emoji;      //establece el contenido(emoji) de la fruta
    div.style.width = `${frutaData.radio * 2}px`;//"perfecciona" la ubicacion del emoji dentro de su circulo
    div.style.height = `${frutaData.radio * 2}px`;
    div.style.fontSize = `${frutaData.radio * 1.2}px`;//acomoda la fruta en el centro del div(circulito que lo rodea)
    div.style.lineHeight = `${frutaData.radio * 2}px`;

    div.addEventListener("dragstart", () => {             
      this.juego.frutaArrastrada = { nivel, elemento: div };//hace de la licuadora un lugar donde se pueden soltar los objetos
    });

    div.addEventListener("dragend", () => {
      this.juego.frutaArrastrada = null;       
    });

    this.elemento.appendChild(div);           //crea la fruta arrastrable en el canasto
  }

  crearBandeja() {
    this.elemento.innerHTML = "";    //limpia el canasto luego de soltar la fruta para que aparezca otra
    for (let i = 0; i < 1; i++) {
      this.agregarFrutaAleatoria();
    }
  }

  agregarFrutaAleatoria() {
    let nivel = Math.floor(Math.random() * 5) + 1;  //genera un numero entre 1-5 para generar una fruta de ese nivel
    this.agregarFrutaADrag(nivel);
  }

  inicializarEventos() {
    // El evento 'drop' se maneja en la clase Juego (Jarra)
  }
}

/**
 * Clase principal que gestiona el estado del juego, la física y la lógica.
 */
class Juego {
  constructor() {
    // ======== ELEMENTOS ========
    //agarramos los elementos para darles "utilidad"
    this.bandejaElemento = document.getElementById("fruits-list");
    this.jarraElemento = document.getElementById("jar");
    this.botonReiniciar = document.getElementById("restartBtn");
    this.textoPuntaje = document.getElementById("score");
    //pantalla de fin de juego o el "game over"
    this.modalOverlay = document.getElementById("end-game-modal");
    this.modalRestartBtn = document.getElementById("modal-restart-btn");
    this.mensajeGuardar = document.getElementById("save-score-msg");
    this.botonMenu = document.getElementById("menu-btn");
    // pantalla de inicio
    this.pantallaInicio = document.getElementById("start-screen");
    this.inputTag = document.getElementById("player-tag");
    this.errorTag = document.getElementById("tag-error");
    this.botonJugar = document.getElementById("play-btn");
    this.inputPassword = document.getElementById("player-password");   // campo de contraseña de la pantalla de inicio
    this.botonCrearCuenta = document.getElementById("register-btn");   // botón "Crear cuenta"
    this.jugador = "";        // tag del jugador de esta sesión (texto que se muestra)
    this.jugadorId = null;    // id del jugador en la base de datos (lo devuelve la API al registrarlo)
    this.registrando = false; // true mientras se espera la respuesta de la API (evita doble click en JUGAR)
    this.token = null;        // token JWT del inicio de sesión; vive solo en memoria (al recargar hay que ingresar de nuevo)

    this.blenderBladeElemento = null;     //indica que el jugo y las cuchillas estan "desactivadas" y pueden ser llamadas en otra funcion
    this.blenderJuiceElemento = null;
    this.blenderBaseElemento = document.getElementById("blender-base"); //base de la licuadora
    this.intervaloJuego = null;
    this.lineaGuiaElemento = this.crearLineaGuia(); 
    this.lineaGameOverElemento = this.crearLineaGameOver();   //para crear la linea de fin de juego y la linea guia que indica donde caen las frutas

    // ======== ESTADO DEL JUEGO ========
    this.frutasEnJarra = [];
    this.frutaArrastrada = null; //se actualizara con los datos de la fruta que se agarre justo cuando empiece el "drag"
    this.puntaje = 0;
    this.fusionesPendientes = [];
    this.frutaCayendo = false; //booleano, indica si todavia hay una fruta cayendo para evitar que otra se lanze
    this.juegoTerminado = false; 

    // ======== COMPONENTES ========
    this.bandeja = new Bandeja(this.bandejaElemento, this); //hace que el canasto sea funcional

    // ======== INICIALIZACIÓN ========(inicio de los metodos)
    this.inicializarEventos();
    this.reiniciarJuego();
    this.cargarRanking();   // llena la tabla de la pantalla de inicio con GET /api/ranking
    this.intervaloJuego = setInterval(this.bucleJuego.bind(this), intervaloFisica); //inicia el bucle que "da vida" al juego
  }

  // MÉTODO PARA CREAR EL ELEMENTO DE LA LÍNEA GUÍA
  crearLineaGuia() {
    const linea = document.createElement("div");
    linea.id = "drop-line-guide";
    this.jarraElemento.appendChild(linea);  //muestra la linea cuando esta dentro de la jarra
    return linea;
  }
  
  // MÉTODO PARA CREAR LA LÍNEA DE GAME OVER
  crearLineaGameOver() {
    const linea = document.createElement("div");
    linea.id = "game-over-line";
    this.jarraElemento.appendChild(linea);    //pone la linea en la jarra
    return linea;
  }

  // ======== MÉTODOS DE ESTADO Y UI ========

  actualizarPuntaje() {
    this.textoPuntaje.textContent = "Puntaje: " + this.puntaje;     //actualiza el puntaje
    
    if (this.puntaje >= PUNTAJE_MAXIMO && !this.juegoTerminado) {   //controla si se supero el puntaje maximo
      this.juegoTerminado = true;
      this.activarLicuadora();
    }
  }

  reiniciarJuego() {    //pone todos los valores de las variables en "0"
    this.frutasEnJarra = []; //elimina todo lo guardado
    
    // Ocultar modal si está visible
    this.modalOverlay.classList.remove("visible");

    // Limpiar y crear los elementos de la licuadora
    this.jarraElemento.innerHTML = '';
    
    //creando las cuchillas
    const blade = document.createElement('div');
    blade.id = 'blender-blade';
    this.jarraElemento.appendChild(blade);
    this.blenderBladeElemento = blade;
    //creando el jugo
    const juice = document.createElement('div');
    juice.id = 'blender-juice';
    juice.className = 'juice-level-0';        //con nivel de jugo en "0%"
    this.jarraElemento.appendChild(juice);
    this.blenderJuiceElemento = juice;
    
    this.lineaGuiaElemento = this.crearLineaGuia(); //crea las lineas nuevamente
    this.lineaGameOverElemento = this.crearLineaGameOver(); 

    this.puntaje = 0;
    this.actualizarPuntaje();
    this.bandeja.crearBandeja(); 
    this.fusionesPendientes = [];   //se asegura de que no queden procesos de fusion de partidas anteriores
    this.frutaCayendo = false;
    this.juegoTerminado = false;

    // Restaurar el modo normal
    document.getElementById("app").classList.remove("blender-mode");
    document.getElementById("tray").style.opacity = 1;
    document.getElementById("tray").style.pointerEvents = 'auto';
    
    // Ocultar la línea guía
    if (this.lineaGuiaElemento) {
        this.lineaGuiaElemento.style.display = 'none'; 
    }
    
    //hace que el juego se ejecute nuevamente luego de que se haya reiniciado
    //si es que la condicion se cumple
    if (!this.intervaloJuego) {
        this.intervaloJuego = setInterval(this.bucleJuego.bind(this), intervaloFisica);
    }
  }


  // ======== MECÁNICA DE JUEGO ========
  crearFrutaEnJarra(nivel, x, y) {
    const nuevaFruta = new Fruta(nivel, x, y);
    this.frutasEnJarra.push(nuevaFruta);
    this.jarraElemento.appendChild(nuevaFruta.elemento);
    return nuevaFruta;        
  }

  bucleJuego() {
    if (this.juegoTerminado) return; //si el juego se termina, el metodo se sale deteniendo todos los posibles "calculos"

    let algunaFrutaEnMovimiento = false; //ve si hay alguna fruta cayendo o en movimiento

    //Aplicar Física (Gravedad y Fricción)
    for (let fruta of this.frutasEnJarra) {
      if (fruta.fusionando) continue; //si la fruta se esta fusionando se salta todo lo demas para no causar "problemas" con su movimiento

      fruta.vy += gravedad;      //para que la fruta acelere hacia abajo
      fruta.vx *= friccion;     //para que las frutas vayan "frenando" sin arrastrarse mucho
      fruta.vy *= friccion;

      fruta.x += fruta.vx;     //para añadir movimiento a la fruta
      fruta.y += fruta.vy;

      //control de colisión con los bordes y el fondo
      this.aplicarColisionBordes(fruta);

      if (Math.abs(fruta.vx) > minVelocidad || Math.abs(fruta.vy) > minVelocidad) {
        algunaFrutaEnMovimiento = true;   //deja avanzar si el valor es falso(cuando no haya movimiento de frutas)
      }
    }

    //Comprobar colisiones entre frutas (para que no se pongan una encima de otra)
    this.comprobarColisiones();

    //Procesar Fusiones
    this.procesarFusiones();

    //Actualizar estado de caída y posición visual
    if (this.frutaCayendo && !algunaFrutaEnMovimiento) {
      this.frutaCayendo = false;      //si la fruta lanzada esta quieta, permite al jugador lanzar la siguiente
    }

    for (let fruta of this.frutasEnJarra) {
      fruta.actualizarPosicionVisual();       //su cosito lo explica(actualiza su posicion visual XD)
    }
    
    //Verificar condición de fin de juego en CADA FRAME
    if (!this.juegoTerminado) { 
      this.verificarLimiteSuperior();
    }
  }

  aplicarColisionBordes(fruta) {
    const radio = fruta.radio;
    const restitucionBorde = 0.1; //hace que los rebotes sean pequeños
    const anchoJarra = this.jarraElemento.clientWidth;
    const altoJarra = this.jarraElemento.clientHeight;

    // Lados (X)
    if (fruta.x - radio < 1) {    //control para que el centro de la fruta no salga ni se atasque fuera de la jarra
      fruta.x = radio;
      fruta.vx = -fruta.vx * restitucionBorde;    //al chocar invierte su direccion para ir en sentido opuesto con menor fuerza
    }
    if (fruta.x + radio > anchoJarra) {
      fruta.x = anchoJarra - radio;
      fruta.vx = -fruta.vx * restitucionBorde;
    }

    // Fondo (Y) 
    if (fruta.y + radio > altoJarra) { // para que la fruta toque con el borde inferior en "exactitud"
      fruta.y = altoJarra - radio;
      fruta.vy = -fruta.vy * restitucionBorde; //al impactar reduce la fuerza de rebote para que no salga volando XD
      fruta.vx *= 0.9; 
    }
  }

  comprobarColisiones() {
    for (let k = 0; k < MAX_ITERACIONES_COLISIONES; k++) {  //se asegura de separar las frutas en caso de que hayan muchas juntas
      for (let i = 0; i < this.frutasEnJarra.length; i++) {
        for (let j = i + 1; j < this.frutasEnJarra.length; j++) {  //comprueba la pareja
          let frutaA = this.frutasEnJarra[i];
          let frutaB = this.frutasEnJarra[j]; 

          if (frutaA.fusionando || frutaB.fusionando) continue;   //si se estan fusionando se salta la parte de colisiones

          let dx = frutaB.x - frutaA.x;     //controla si hay una distancia "considerable" entre el centro de ambas frutas
          let dy = frutaB.y - frutaA.y;
          let distancia = Math.sqrt(dx * dx + dy * dy);
          let radioSuma = frutaA.radio + frutaB.radio;  

          if (distancia < radioSuma) {    //indica que hay colision
            this.resolverColision(frutaA, frutaB, dx, dy, distancia, radioSuma); //resuelve colision

            if (frutaA.nivel === frutaB.nivel) {
              this.fusionesPendientes.push([frutaA, frutaB]);   
            }
          }
        }
      }
    }
  }

  resolverColision(frutaA, frutaB, dx, dy, distancia, radioSuma) {
    let normalX = dx / distancia; 
    let normalY = dy / distancia;
    let superposicion = radioSuma - distancia;

    // Separación (Desplazamiento)
    const totalMovimiento = superposicion + 0.01; 
    const movimientoX = normalX * (totalMovimiento / 2);//calculo de cuanto deben moverse al chocar
    const movimientoY = normalY * (totalMovimiento / 2);
    frutaA.x -= movimientoX;    //frutaA se mueve en direccion opuesta
    frutaA.y -= movimientoY;
    frutaB.x += movimientoX;    //frutaB se mueve en direccion opuesta
    frutaB.y += movimientoY;

    // Impulso (simulacion de rebote)
    let velocidadRelativaX = frutaB.vx - frutaA.vx;
    let velocidadRelativaY = frutaB.vy - frutaA.vy;
    let impulso = normalX * velocidadRelativaX + normalY * velocidadRelativaY;//calculo de velocidad entre frutas

    if (impulso < 0) {    //revisa si las frutas se estan acercando(valor -) o alejando(valor +)
      let impulsoAjustado = (1 + coeficienteRestitucion) * impulso;//calculo para saber el impuslo para separarse

      let totalRadio = frutaA.radio + frutaB.radio; //calculo de peso
      let pesoA = frutaA.radio / totalRadio; 
      let pesoB = frutaB.radio / totalRadio;

      frutaA.vx += impulsoAjustado * normalX * pesoB; //impulso que lo separa de la frutaB
      frutaA.vy += impulsoAjustado * normalY * pesoB;
      frutaB.vx -= impulsoAjustado * normalX * pesoA; //impulso que lo separa de la frutaA
      frutaB.vy -= impulsoAjustado * normalY * pesoA;
    }
  }

  procesarFusiones() {
    const fusionesRealizadas = new Set();
    for (const [f1, f2] of this.fusionesPendientes) {
      if (f1.fusionando || f2.fusionando || fusionesRealizadas.has(f1.id) || fusionesRealizadas.has(f2.id)) {
        continue; //control para evitar errores
      }

      f1.fusionando = true;
      f2.fusionando = true;
      fusionesRealizadas.add(f1.id);
      fusionesRealizadas.add(f2.id);

      this.fusionarFrutas(f1, f2);
    }
    this.fusionesPendientes = [];
  }

  fusionarFrutas(f1, f2) {
    if (!f1 || !f2) return;

    let nuevoPuntaje = f1.puntaje;

    // Animación de desaparición 
    f1.elemento.classList.add('fading');
    f2.elemento.classList.add('fading');
    
    setTimeout(() => {
        // Remover frutas viejas
        this.jarraElemento.removeChild(f1.elemento);
        this.jarraElemento.removeChild(f2.elemento);
        this.frutasEnJarra = this.frutasEnJarra.filter(f => f.id !== f1.id && f.id !== f2.id);

        let nuevoNivel = f1.nivel + 1;
        const nivelSandia = FRUTAS_DATA.length; 

        let nuevaX = (f1.x + f2.x) / 2; //calcula el centro de dos frutas para que su fusion aparezca ahi
        let nuevaY = (f1.y + f2.y) / 2;

        this.mostrarPuntuacionFlotante(nuevoPuntaje, nuevaX, nuevaY); 

        // Lógica de bonus y final para la fusión de sandías (Nivel 12 + Nivel 12)
        if (f1.nivel === nivelSandia && nuevoNivel > FRUTAS_DATA.length) {
          this.puntaje += nuevoPuntaje; 
          this.mostrarEfectoExplosion(nuevaX, nuevaY);
          this.actualizarPuntaje();
          return; 
        }

        // Lógica normal de fusión
        if (nuevoNivel > FRUTAS_DATA.length) return;

        this.puntaje += nuevoPuntaje;
        this.actualizarPuntaje();   //control por la fusion
        let nuevaFruta = this.crearFrutaEnJarra(nuevoNivel, nuevaX, nuevaY);

        nuevaFruta.vy = 0;
        nuevaFruta.vx = 0;

        this.frutaCayendo = true;
    }, 200); //200 milisegundos para asegurarse de que la animacion se vea antes de que las frutas desaparezcan
  }
  
  // MÉTODO PARA PUNTUACIÓN FLOTANTE
  mostrarPuntuacionFlotante(puntos, x, y) {
    const scoreElement = document.createElement('div');
    scoreElement.textContent = `+${puntos}`;
    scoreElement.className = 'floating-score';
    
          //ajusta la posición para que esté centrada en la fusión
    scoreElement.style.left = `${x}px`;
    scoreElement.style.top = `${y}px`;
    
    this.jarraElemento.appendChild(scoreElement); //hace que el puntaje flotante salga en la jarra
    
    //remueve el elemento después de que la animación termine (1 segundo)
    setTimeout(() => {
        this.jarraElemento.removeChild(scoreElement);
    }, 1000);
  }

  mostrarEfectoExplosion(x, y) {
    const numEstrellas = 15;
    const emoji = '⭐';

    for (let i = 0; i < numEstrellas; i++) {    //bucle que crea las estrellas
      const star = document.createElement('div');
      star.textContent = emoji;
      star.className = 'explosion-star';

      star.style.left = `${x}px`;
      star.style.top = `${y}px`;

      const angle = Math.random() * 2 * Math.PI;
      const speed = Math.random() * 60 + 50;

      const finalX = x + Math.cos(angle) * speed;   //calculo de posicion final de la estrella
      const finalY = y + Math.sin(angle) * speed;

      this.jarraElemento.appendChild(star);

      setTimeout(() => {
        star.style.transform = `translate(${finalX - x}px, ${finalY - y}px)`; //de posicion inicial a final
        star.style.opacity = '0';   //mientrtas se mueve la estrella se hace mas grande y transparente
        star.style.fontSize = '24px';
      }, 10);

      setTimeout(() => {
        this.jarraElemento.removeChild(star);
      }, 1000);//desaparece despues de 1 segundo
    }
  }

  // El nivel de jugo depende del puntaje respecto de la capacidad total (PUNTAJE_MAXIMO),
  // no de cuántas frutas queden en el frasco. 5/5 solo si llegaste cerca del máximo.
  calcularNivelJugo() {
    const porcentajeLlenado = Math.min(1, this.puntaje / PUNTAJE_MAXIMO);

    if (porcentajeLlenado === 0) return 0;
    if (porcentajeLlenado <= 0.2) return 1;    // hasta 3000 pts
    if (porcentajeLlenado <= 0.4) return 2;    // hasta 6000 pts
    if (porcentajeLlenado <= 0.65) return 3;   // hasta 9750 pts
    if (porcentajeLlenado <= 0.85) return 4;   // hasta 12750 pts
    return 5;
  }

  activarLicuadora() {
    clearInterval(this.intervaloJuego); 
    this.intervaloJuego = null; //detiene el bucle del juego al empezar la animacion

    document.getElementById("app").classList.add("blender-mode");
    this.blenderBladeElemento.classList.add("blade-spinning");
    document.getElementById("tray").style.opacity = 0;            //desavtiva el canasto y funciones
    document.getElementById("tray").style.pointerEvents = 'none';

    const frutasParaLicuar = [...this.frutasEnJarra].sort((a, b) => b.y - a.y); //"licua" de menor a mayor

    let delay = 0;
    for (let i = 0; i < frutasParaLicuar.length; i++) {
        const fruta = frutasParaLicuar[i];
        
        const rect = this.jarraElemento.getBoundingClientRect();
        // Usar la posición del centro de la jarra para el vórtice
        const centroX = this.jarraElemento.clientWidth / 2;
        const centroY = this.jarraElemento.clientHeight; 

        setTimeout(() => {
            // Animación de movimiento al centro (Vórtice)
            fruta.elemento.style.transition = 'transform 0.4s ease-in, opacity 0.5s ease-out';
            // Calcular el desplazamiento relativo al centro
            const dx = centroX - fruta.x;
            const dy = centroY - fruta.y - (fruta.radio * 2); 
            fruta.elemento.style.transform = `translate(${dx}px, ${dy}px) scale(0.5)`;
        }, delay);
        
        setTimeout(() => {
            // Aplicar la desaparición
            fruta.elemento.classList.add("blending");
        }, delay + 300); 

        delay += 100;
    }

    setTimeout(() => {
      const nivelJugo = this.calcularNivelJugo();
      
      this.frutasEnJarra.forEach(f => f.elemento.remove());//vacia el array y se asegura de que no quede ninguna fruta dentro de la jarra
      this.frutasEnJarra = [];
      //lena la jarra con el jugo
      this.blenderJuiceElemento.classList.remove('juice-level-0', 'juice-level-1', 'juice-level-2', 'juice-level-3', 'juice-level-4', 'juice-level-5');
      this.blenderJuiceElemento.classList.add(`juice-level-${nivelJugo}`);  //
      //espera 2,5 segundos y luego esconde las cuchillas y llama al modal
      setTimeout(() => {
        this.blenderBladeElemento.classList.remove("blade-spinning"); 
        
        // Mostrar el modal de fin de juego (Jugar de nuevo / Menú principal)
        this.mostrarModalFinDeJuego(nivelJugo, "¡Jugo Listo! 🍹");

      }, 2500); 

    }, delay + 1000); 
  }
  
  // MÉTODO PARA MOSTRAR EL MODAL
  mostrarModalFinDeJuego(nivelJugo, titulo = "¡Jugo Listo! 🍹") {
      const titleElement = document.getElementById("modal-header-title");
      if (titleElement) {
        titleElement.textContent = titulo;
      }
      //muestra puntaje y nivel el nivel del jugo
      document.getElementById("modal-score").textContent = this.puntaje;
      document.getElementById("modal-juice-level").textContent = `${nivelJugo}`;
      
      // Personalizar el emoji según el nivel de jugo o estado de Game Over
      let emoji = '😢';
      if (titulo.includes("CAJA LLENA")) emoji = '💀'; // Emoji específico para Game Over por límite
      else if (nivelJugo >= 5) emoji = '🏆';
      else if (nivelJugo >= 3) emoji = '🌟';
      else if (nivelJugo >= 1) emoji = '👍';
      
      document.getElementById("modal-emoji").textContent = emoji;

      this.modalOverlay.classList.add("visible");

      // Guarda la partida en la base de datos con el tag del jugador
      this.guardarPuntaje(this.puntaje, nivelJugo,
        titulo.includes("CAJA LLENA") ? "caja_llena" : "jugo");
  }

  // ======== PANTALLA DE INICIO Y COMUNICACIÓN CON LA API REST ========

  // Función auxiliar: hace una petición a la API y devuelve { estado, ok, datos }.
  // ruta: parte final de la URL (ej: "/jugadores"), metodo: GET/POST/..., cuerpo: objeto a enviar como JSON
  async llamarApi(ruta, metodo = "GET", cuerpo = null) {
    // Opciones de la petición: el método y que se espera JSON como respuesta
    const opciones = { method: metodo, headers: { "Accept": "application/json" } };

    // Si ya iniciamos sesión, se adjunta el token en el encabezado Authorization (formato "Bearer <token>")
    if (this.token) {
      opciones.headers["Authorization"] = `Bearer ${this.token}`;
    }

    // Si hay datos para enviar, se avisa que van en formato JSON y se convierten a texto JSON
    if (cuerpo !== null) {
      opciones.headers["Content-Type"] = "application/json";
      opciones.body = JSON.stringify(cuerpo);
    }

    // Envía la petición a la API y espera la respuesta (si no hay conexión, lanza un error que atrapa quien llama)
    const respuesta = await fetch(`${API_URL}${ruta}`, opciones);

    // Variable para el JSON de la respuesta (null si la respuesta no trae JSON válido)
    let datos = null;
    try {
      datos = await respuesta.json();   // convierte el cuerpo de la respuesta a objeto JavaScript
    } catch (err) {
      datos = null;                     // si no era JSON, se deja en null
    }

    // Devuelve el código HTTP (200, 201, 400, ...), si fue exitosa y los datos recibidos
    return { estado: respuesta.status, ok: respuesta.ok, datos: datos };
  }

  // Lee tag y contraseña del formulario de inicio; si falta alguno, muestra el error y devuelve null
  leerFormularioInicio() {
    // Tag sin espacios en los costados; la contraseña se deja tal cual (los espacios cuentan)
    const tag = this.inputTag.value.trim();
    const password = this.inputPassword.value;

    // Si falta alguno de los dos, avisa y no continúa
    if (!tag || !password) {
      this.errorTag.textContent = "Escribí tu tag y tu contraseña 🍓";
      return null;
    }

    // Devuelve los datos listos para enviar a la API
    return { tag: tag, password: password };
  }

  // Bloquea o libera los botones del formulario mientras se espera a la API (evita dobles clicks)
  bloquearFormulario(bloquear) {
    this.registrando = bloquear;                  // recuerda si hay una petición en curso
    this.botonJugar.disabled = bloquear;          // deshabilita (o habilita) JUGAR
    this.botonCrearCuenta.disabled = bloquear;    // deshabilita (o habilita) Crear cuenta
  }

  // Pide un token con POST /api/auth/login. Devuelve true si salió bien (y guarda token y jugador);
  // si falla, escribe el motivo en pantalla y devuelve false.
  async autenticar(tag, password) {
    // Envía las credenciales a la API
    const { estado, datos } = await this.llamarApi("/auth/login", "POST", { tag: tag, password: password });

    // 200 OK: las credenciales son correctas y la API devolvió el token
    if (estado === 200 && datos && datos.token) {
      this.token = datos.token;               // guarda el token para las próximas peticiones
      this.jugador = datos.jugador.tag;       // tag tal como quedó guardado
      this.jugadorId = datos.jugador.id;      // id del jugador (lo usa licuarManual para saber si hay partida)
      return true;
    }

    // 401: tag o contraseña incorrectos
    if (estado === 401) {
      this.errorTag.textContent = "Tag o contraseña incorrectos";
    // 400: faltan datos
    } else if (estado === 400) {
      this.errorTag.textContent = "Escribí tu tag y tu contraseña 🍓";
    // Cualquier otro código (ej: 500) es un problema del servidor
    } else {
      this.errorTag.textContent = "El servidor no pudo iniciar sesión";
    }
    return false;
  }

  // Cierra la pantalla de inicio y empieza una partida limpia
  empezarPartida() {
    this.inputPassword.value = "";                    // borra la contraseña escrita (no queda en pantalla)
    this.errorTag.textContent = "";                   // limpia mensajes de error anteriores
    this.pantallaInicio.classList.add("oculta");      // esconde la pantalla de inicio
    this.reiniciarJuego();                            // arranca una partida limpia
  }

  // Botón JUGAR: inicia sesión con el tag y la contraseña, y si salió bien empieza la partida
  async iniciarSesion() {
    // Si ya hay una petición en curso, se ignora el click repetido
    if (this.registrando) return;

    // Lee el formulario; si falta algo, se corta acá
    const datos = this.leerFormularioInicio();
    if (!datos) return;

    // Bloquea los botones y limpia el error anterior
    this.bloquearFormulario(true);
    this.errorTag.textContent = "";

    try {
      // Intenta iniciar sesión; si funciona, empieza el juego
      if (await this.autenticar(datos.tag, datos.password)) {
        this.empezarPartida();
      }
    } catch (err) {
      // fetch falló: no hay conexión con el servidor
      this.errorTag.textContent = "No hay conexión con el servidor";
    } finally {
      // Pase lo que pase, se liberan los botones
      this.bloquearFormulario(false);
    }
  }

  // Botón "Crear cuenta": registra el tag con POST /api/jugadores, inicia sesión y empieza la partida
  async crearCuenta() {
    // Si ya hay una petición en curso, se ignora el click repetido
    if (this.registrando) return;

    // Lee el formulario; si falta algo, se corta acá
    const datos = this.leerFormularioInicio();
    if (!datos) return;

    // Bloquea los botones y limpia el error anterior
    this.bloquearFormulario(true);
    this.errorTag.textContent = "";

    try {
      // Registra al jugador nuevo (la API valida el tag y la contraseña)
      const { estado, datos: respuesta } = await this.llamarApi("/jugadores", "POST", datos);

      if (estado === 201) {
        // Cuenta creada: se inicia sesión de inmediato con los mismos datos
        if (await this.autenticar(datos.tag, datos.password)) {
          this.empezarPartida();
        }
      } else if (estado === 409) {
        // El tag ya está registrado por otra persona
        this.errorTag.textContent = "Ese tag ya existe. Elegí otro, o ingresá con JUGAR si es tuyo";
      } else if (estado === 400) {
        // La API rechazó el tag o la contraseña: se muestra el motivo que envía
        const detalles = (respuesta && respuesta.detalles) || {};
        this.errorTag.textContent = detalles.tag || detalles.password || "Datos inválidos";
      } else {
        // Cualquier otro código (ej: 500) es un problema del servidor
        this.errorTag.textContent = "El servidor no pudo crear la cuenta";
      }
    } catch (err) {
      // fetch falló: no hay conexión con el servidor
      this.errorTag.textContent = "No hay conexión con el servidor";
    } finally {
      // Pase lo que pase, se liberan los botones
      this.bloquearFormulario(false);
    }
  }

  // Botón ON / OFF: licúa lo que haya en el frasco y termina la partida en el momento
  licuarManual() {
    // Si no hay partida en curso o ya se está licuando, no hace nada
    if (!this.jugadorId || this.juegoTerminado) return;

    // Marca el juego como terminado para frenar la física y los arrastres
    this.juegoTerminado = true;

    // Lanza la animación de licuado; al final muestra el modal y guarda el puntaje
    this.activarLicuadora();
  }

  // Botón "Menú principal" del modal: vuelve a la pantalla de inicio
  volverAlMenu() {
    // Esconde el modal de fin de juego
    this.modalOverlay.classList.remove("visible");

    // Cierra la sesión: olvida al jugador y descarta el token (cada sesión ingresa con su propia cuenta)
    this.jugador = "";
    this.jugadorId = null;
    this.token = null;
    this.inputTag.value = "";
    this.inputPassword.value = "";

    // Muestra de nuevo la pantalla de inicio y deja el cursor en el input
    this.pantallaInicio.classList.remove("oculta");
    this.inputTag.focus();

    // Actualiza la tabla de puntajes con los datos más recientes
    this.cargarRanking();
  }

  // Guarda la partida terminada con POST /api/partidas y muestra el resultado en el modal
  async guardarPuntaje(puntaje, nivelJugo, resultado) {
    // Con 0 puntos no se guarda nada
    if (puntaje <= 0) {
      this.mensajeGuardar.textContent = "Con 0 puntos no entrás al ranking";
      return;
    }

    // Avisa que se está guardando
    this.mensajeGuardar.textContent = "Guardando puntaje...";

    try {
      // Envía la partida a la API; el jugador NO se envía: la API lo toma del token (así nadie guarda puntajes ajenos)
      const { estado, datos } = await this.llamarApi("/partidas", "POST", {
        puntaje: puntaje,
        nivel_jugo: nivelJugo,
        resultado: resultado
      });

      // 401: el token venció o ya no es válido
      if (estado === 401) {
        this.mensajeGuardar.textContent = "Tu sesión venció. Volvé al menú e ingresá de nuevo para guardar puntajes";
        return;
      }

      // 201 Created significa que se guardó; cualquier otro código es un error
      if (estado !== 201) {
        this.mensajeGuardar.textContent = (datos && datos.error) || "No se pudo guardar el puntaje";
        return;
      }

      // La API devuelve si fue récord personal, su mejor puntaje y su puesto en el ranking global
      const r = datos.ranking;
      this.mensajeGuardar.textContent = r.es_record
        ? `¡Nuevo récord, ${this.jugador}! Puesto #${r.posicion} 🎉`
        : `Tu mejor puntaje sigue siendo ${r.mejor} (puesto #${r.posicion})`;

      // Actualiza la tabla de la pantalla de inicio con el nuevo ranking
      this.cargarRanking();
    } catch (err) {
      // fetch falló: no hay conexión con el servidor
      this.mensajeGuardar.textContent = "No hay conexión con el servidor";
    }
  }

  // Dibuja el ranking en la tabla con los datos de GET /api/ranking
  async cargarRanking() {
    // Referencia al cuerpo de la tabla donde van las filas
    const tbody = document.querySelector("#ranking-table tbody");

    // Función local: reemplaza todas las filas por una sola con un mensaje
    const mostrarMensaje = (texto) => {
      tbody.innerHTML = "";                          // borra las filas actuales
      const tr = document.createElement("tr");       // crea una fila
      const td = document.createElement("td");       // crea una celda
      td.colSpan = 4;                                // la celda ocupa las 4 columnas
      td.className = "vacio";                        // estilo gris de mensaje
      td.textContent = texto;                        // escribe el mensaje
      tr.appendChild(td);                            // pone la celda en la fila
      tbody.appendChild(tr);                         // pone la fila en la tabla
    };

    try {
      // Pide a la API los 10 mejores jugadores
      const { ok, datos } = await this.llamarApi("/ranking?limite=10");

      // Si la API respondió con error, se avisa en la tabla
      if (!ok || !Array.isArray(datos)) {
        mostrarMensaje("No se pudo cargar el ranking");
        return;
      }

      // Si todavía no hay puntajes, se muestra un mensaje
      if (datos.length === 0) {
        mostrarMensaje("Todavía no hay puntajes");
        return;
      }

      // Vacía la tabla para volver a dibujarla
      tbody.innerHTML = "";

      // Medallas para los tres primeros puestos
      const medallas = ["🥇", "🥈", "🥉"];

      // Crea una fila por cada jugador del ranking
      datos.forEach((fila, i) => {
        const tr = document.createElement("tr");     // fila nueva
        const celdas = [
          [medallas[i] || String(i + 1), ""],        // puesto (medalla o número)
          [fila.jugador, "r-nombre"],                // tag del jugador
          [fila.puntaje, "r-puntos"],                // puntaje de su mejor partida
          [`${fila.nivel_jugo}/5`, ""]               // nivel de jugo sobre 5
        ];

        // Crea cada celda de la fila
        for (const [texto, clase] of celdas) {
          const td = document.createElement("td");   // celda nueva
          td.textContent = texto;                    // textContent: nunca interpreta HTML (evita inyección)
          if (clase) td.className = clase;           // aplica la clase de estilo si tiene
          tr.appendChild(td);                        // agrega la celda a la fila
        }

        tbody.appendChild(tr);                       // agrega la fila a la tabla
      });
    } catch (err) {
      // fetch falló: no hay conexión con el servidor
      mostrarMensaje("No se pudo conectar con el servidor");
    }
  }

  verificarLimiteSuperior() {
    // Usa la constante global para la detección precisa
    const limiteSuperiorY = LIMITE_SUPERIOR_GAME_OVER; 
    
    for (let f of this.frutasEnJarra) {
      
      // Solo si la fruta ha cruzado la línea de muerte (borde superior < LIMITE)
      if (f.y - f.radio < limiteSuperiorY) { 
          
          //El Game Over solo se activa si la fruta está quieta
          if (Math.abs(f.vy) < minVelocidad && Math.abs(f.vx) < minVelocidad) {
              this.mostrarModalCajaLlena(); 
              return;
          }
      }
    }
  }

  mostrarModalCajaLlena() {
    if (this.juegoTerminado) return; 
    
    //Marcar fin de juego
    this.juegoTerminado = true; 
    
    //Detener el bucle de física inmediatamente
    clearInterval(this.intervaloJuego);
    this.intervaloJuego = null; 
    
    //Ocultar la bandeja de frutas
    document.getElementById("tray").style.opacity = 0;
    document.getElementById("tray").style.pointerEvents = 'none';

    //Mostrar el modal de fin de juego con mensaje de Game Over
    this.mostrarModalFinDeJuego(this.calcularNivelJugo(), "¡CAJA LLENA! 💀"); 
  }

  // ======== DRAG & DROP & EVENTOS ========

  inicializarEventos() {
    this.jarraElemento.addEventListener("dragover", this.manejarDragOver.bind(this)); 
    this.jarraElemento.addEventListener("dragleave", this.manejarDragLeave.bind(this)); //oculta la linea guia fuera de la jarra
    this.jarraElemento.addEventListener("drop", this.manejarDrop.bind(this));
    this.botonReiniciar.addEventListener("click", this.licuarManual.bind(this));   // botón ON / OFF
    
    // Evento para el botón de reiniciar dentro del modal
    this.modalRestartBtn.addEventListener("click", this.reiniciarJuego.bind(this));
    // Pantalla de inicio y menú
    this.botonJugar.addEventListener("click", this.iniciarSesion.bind(this));        // JUGAR = iniciar sesión
    this.botonCrearCuenta.addEventListener("click", this.crearCuenta.bind(this));    // Crear cuenta = registrarse
    this.inputTag.addEventListener("keydown", (e) => {
      if (e.key === "Enter") this.inputPassword.focus();   // Enter en el tag pasa al campo de contraseña
    });
    this.inputPassword.addEventListener("keydown", (e) => {
      if (e.key === "Enter") this.iniciarSesion();         // Enter en la contraseña inicia sesión
    });
    this.botonMenu.addEventListener("click", this.volverAlMenu.bind(this));
  }
  
  //Muestra la línea guía en la posición X del cursor
  manejarDragOver(e) {
    e.preventDefault(); //para indicar que la jarra es un lugar en el que se pueden soltar objetos
    if (this.juegoTerminado || !this.frutaArrastrada) { //oculta la linea guia si el juego termino o una fruta ya fue arrastrada
        this.lineaGuiaElemento.style.display = 'none';
        return; 
    }
    
    const rect = this.jarraElemento.getBoundingClientRect();  //posicion de la jarra en pantalla
    const xRelativo = e.clientX - rect.left;
    const yRelativo = e.clientY - rect.top;

    // muestra la linea guia si cumple la condicion(el cursor debe estar al borde de la jarra)
    if (yRelativo > this.jarraElemento.clientHeight * 0.05) { 
        this.lineaGuiaElemento.style.display = 'none';
        return; 
    }
    
    // La línea se muestra en la posición X del cursor
    this.lineaGuiaElemento.style.left = `${xRelativo - 1}px`; 
    this.lineaGuiaElemento.style.display = 'block';
  }
  
  //Oculta la línea guía al salir de la jarra
  manejarDragLeave(e) {
    this.lineaGuiaElemento.style.display = 'none';
  }

  manejarDrop(e) {
    e.preventDefault();
    if (this.juegoTerminado || !this.frutaArrastrada) return; 
    
    // Ocultar la línea guía al soltar
    this.lineaGuiaElemento.style.display = 'none'; 

    const rect = this.jarraElemento.getBoundingClientRect();
    const yRelativo = e.clientY - rect.top;

    // Solo se permite soltar en la parte superior(en este caso al 5%)
    if (yRelativo > this.jarraElemento.clientHeight * 0.05) {
      alert("Solo podés soltar frutas desde la parte superior del frasco 🍓");
      return;
    }

    const frutaData = FRUTAS_DATA[this.frutaArrastrada.nivel - 1];
    const x = e.clientX - rect.left;//fija la posicion X (horizontal arriba de la jarra)
    const y = 0 + frutaData.radio; //fija la posicion Y (vertical encima de la zona donde se sueltan)

    let nuevaFruta = this.crearFrutaEnJarra(this.frutaArrastrada.nivel, x, y);
    nuevaFruta.haSidoSoltada = true; 

    // Darle un pequeño impulso hacia abajo para que empiece a caer
    nuevaFruta.vy = 2;

    this.frutaCayendo = true;

    this.frutaArrastrada.elemento.remove();
    this.bandeja.agregarFrutaAleatoria();
    this.frutaArrastrada = null; 
  }
}
//Iniciar el juego
document.addEventListener('DOMContentLoaded', () => {
  new Juego();
});