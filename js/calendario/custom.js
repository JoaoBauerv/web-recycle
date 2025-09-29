document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');

    const cadastrarModal = new bootstrap.Modal(document.getElementById('cadastrarModal'));

    var calendar = new FullCalendar.Calendar(calendarEl, {
      
        themeSystem: 'bootstrap5',

        headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },

        //idioma
        locale: 'pt-br',

        //data inicial
        //initialDate: '2025-09-24',

        navLinks: true, // permite clicar nos nomes dos dias da semana

        //permitir clicar e arrastar o mouse sobre um ou varios dias no calendario
        selectable: true,

        //indicar visualmente a area que sera selecionada antes que o usuario solte o botao do mouse para confirmar a selecao
        selectMirror: true,

        //permitir arrastar e redimensionar os eventos diretamente no calendario
        editable: true,

        //numero maximo de eventos em um determinado dia, se for true, o numero de eventos sera limitado a altura da celula do dia
        dayMaxEvents: true, 

        events: '../../functions/calendario/listar.php',

        //clique do usuario sobre o evento
        eventClick: function(info){

            //receber o seletor do modal
            const visualizarModal = new bootstrap.Modal(document.getElementById('visualizarModal'));

            //enviar informaçoes para o modal
            document.getElementById('visualizar_id').innerText = info.event.id;
            document.getElementById('visualizar_title').innerText = info.event.title;
            document.getElementById('visualizar_start').innerText = info.event.start.toLocaleString();
            document.getElementById('visualizar_end').innerText = info.event.end !== null ? info.event.end.toLocaleString() : info.event.start.toLocaleString();

            //abrir modal
            visualizarModal.show();
        },
        
        //abrir janela de cadastrar evento
        select: function(info){
            console.log(info);

            document.getElementById('cad_start').value = converterData(info.start);
            document.getElementById('cad_end').value = converterData(info.end);


            cadastrarModal.show();

        }

    });

    calendar.render();


    //funcao de converter a data
    function converterData(data) {
        const dataObj = new Date(data);

        const ano = dataObj.getFullYear();

        const mes = String(dataObj.getMonth() + 1).padStart(2, '0');

        const dia = String(dataObj.getDate()).padStart(2, '0');

        const hora = String(dataObj.getHours()).padStart(2, '0');

        const minuto = String(dataObj.getMinutes()).padStart(2, '0');

        // Return in ISO 8601 format for datetime-local input: YYYY-MM-DDTHH:MM
        return `${ano}-${mes}-${dia}T${hora}:${minuto}`;
    };


    const formCadEvento = document.getElementById('formCadEvento');

    const msg = document.getElementById("msg");

    const btnCadEvento = document.getElementById("btnCadEvento");

    if(formCadEvento){

        formCadEvento.addEventListener("submit", async (e) => {

            e.preventDefault();

            btnCadEvento.value= "Salvando...";

            const dadosForm = new FormData(formCadEvento);

            const dados = await fetch("../../functions/calendario/cadastrar.php", {
                method: "POST",
                body: dadosForm
            })

            const resposta = await dados.json();

            if(!resposta['status']){
                //document.getElementById('msgCadEvento').innerHTML = `<div class="alert alert-danger" role="alert">${resposta['msg']}</div>`;
                alert(resposta['msg']); // alerta de erro

            }else{

                // msg.innerHTML = `<div class="alert alert-success" role="alert">${resposta['msg']} </div>`;
                alert(resposta['msg']); // alerta de sucesso

                formCadEvento.reset();

                const novoEvento = {
                    id: resposta['id'],
                    title: resposta['title'],
                    color: resposta['color'],
                    start: resposta['start'],
                    end: resposta['end'],
                }

                calendar.refetchEvents();

                cadastrarModal.hide();

            }

            btnCadEvento.value = "Cadastrar";
        })
       

    }

    
  })