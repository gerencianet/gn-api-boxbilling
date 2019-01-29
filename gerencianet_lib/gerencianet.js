function sendData(data)
{   
    var modal = document.getElementById('myModal');
    var span = document.getElementsByClassName("gn-close")[0];

    $('body').addClass('waiting-process');
    document.getElementById("gnbutton").disabled = true;
    data.customer_document = $("#customer-document").val();
    $.post("bb-library/Payment/Adapter/gerencianet_lib/GenerateBillet.php",data,
    function(retorno){
        $('body').removeClass('waiting-process');
        if(retorno.code == 200)
            window.location.replace(retorno.data.pdf.charge);
        else{
            $('#gn-modal-body').html(retorno.message);
            modal.style.display = "block";
        }
    }, "json");

    span.onclick = function() {
        modal.style.display = "none";
        document.getElementById("gnbutton").disabled = false;
    }
}


function verifyCPF(cpf) 
{
    if (cpf) 
    {
        cpf = cpf.replace(/[^\d]+/g,'');

        if(cpf == '' || cpf.length != 11) return false;

        var resto;
        var soma = 0;

        if (cpf == "00000000000" || cpf == "11111111111" || cpf == "22222222222" || cpf == "33333333333" || cpf == "44444444444" || cpf == "55555555555" || cpf == "66666666666" || cpf == "77777777777" || cpf == "88888888888" || cpf == "99999999999" || cpf == "12345678909") return false;

        for (i=1; i<=9; i++) soma = soma + parseInt(cpf.substring(i-1, i)) * (11 - i);
            resto = (soma * 10) % 11;

        if ((resto == 10) || (resto == 11))  resto = 0;
        if (resto != parseInt(cpf.substring(9, 10)) ) return false;

        soma = 0;
        for (i = 1; i <= 10; i++) soma = soma + parseInt(cpf.substring(i-1, i)) * (12 - i);
            resto = (soma * 10) % 11;

        if ((resto == 10) || (resto == 11))  resto = 0;
        if (resto != parseInt(cpf.substring(10, 11) ) ) return false;
        return true;
    } else {
        return false;
    }
}

function verifyCNPJ(cnpj) {
    if (cnpj) {
        cnpj = cnpj.replace(/[^\d]+/g,'');

        if(cnpj == '' || cnpj.length != 14) return false;

        if (cnpj == "00000000000000" || cnpj == "11111111111111" || cnpj == "22222222222222" || cnpj == "33333333333333" || cnpj == "44444444444444" || cnpj == "55555555555555" || cnpj == "66666666666666" || cnpj == "77777777777777" || cnpj == "88888888888888" || cnpj == "99999999999999") return false;

        var tamanho = cnpj.length - 2
        var numeros = cnpj.substring(0,tamanho);
        var digitos = cnpj.substring(tamanho);
        var soma = 0;
        var pos = tamanho - 7;

        for (i = tamanho; i >= 1; i--) {
          soma += numeros.charAt(tamanho - i) * pos--;
          if (pos < 2)
            pos = 9;
        }

        var resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;

        if (resultado != digitos.charAt(0)) return false;

        tamanho = tamanho + 1;
        numeros = cnpj.substring(0,tamanho);
        soma = 0;
        pos = tamanho - 7;

        for (i = tamanho; i >= 1; i--) {
          soma += numeros.charAt(tamanho - i) * pos--;
          if (pos < 2) pos = 9;
        }

        resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;

        if (resultado != digitos.charAt(1)) return false;

        return true; 
    } 
    else {
        return false;
    }
}

function valida(o){
    v_obj=o
    v_obj2=document.getElementById("gnbutton");
    var customerDocTF = document.getElementById("customer-document");


    v = v_obj.value;
    v=v.replace(/\D/g,"");
    if (v.length <= 11) 
    { 
        if(!verifyCPF(v) && (customerDocTF.checked == false)) 
        {
            v_obj2.disabled=true;
            alert("CPF inválido.");
        }
        else v_obj2.disabled=false;
    } 
    else
    {
        if(!verifyCNPJ(v) && (customerDocTF.checked == false))
        {
            v_obj2.disabled=true;
            alert("CNPJ inválido.");
        }
        else v_obj2.disabled=false;
    }
}

function mascaraMutuario(o,f)
{
    v_obj=o
    v_fun=f
    setTimeout("execmascara()",1)
}

function execmascara(){
    v_obj.value=v_fun(v_obj.value)
}

function cpfCnpj(v)
{
    v=v.replace(/\D/g,"")

    if (v.length < 14) 
    { 
        v=v.replace(/(\d{3})(\d)/,"$1.$2");
        v=v.replace(/(\d{3})(\d)/,"$1.$2");

        v=v.replace(/(\d{3})(\d{1,2})$/,"$1-$2");

    } 
    else if (v.length == 14) 
    {
        v=v.replace(/^(\d{2})(\d)/,"$1.$2");
        v=v.replace(/^(\d{2})\.(\d{3})(\d)/,"$1.$2.$3");
        v=v.replace(/\.(\d{3})(\d)/,".$1/$2");
        v=v.replace(/(\d{4})(\d)/,"$1-$2");

    }
    else
    {   v=v.substring(0,(v.length - 1));
        v=v.replace(/^(\d{2})(\d)/,"$1.$2");
        v=v.replace(/^(\d{2})\.(\d{3})(\d)/,"$1.$2.$3");
        v=v.replace(/\.(\d{3})(\d)/,".$1/$2");
        v=v.replace(/(\d{4})(\d)/,"$1-$2");
    }
    return v;
}
