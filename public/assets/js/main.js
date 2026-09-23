/**
 * FOOD HUB — front-end comum
 * Funções compartilhadas entre telas: tema claro/escuro,
 * validação de formulário (Bootstrap) e feedback via toast
 * (RF12 / RNF07 — proibido usar alert() nativo).
 */

const FH_THEME_KEY = "fh-theme";

function fhApplyTheme(theme) {
  document.documentElement.setAttribute("data-theme", theme);
}

function fhInitTheme() {
  const saved = localStorage.getItem(FH_THEME_KEY);
  fhApplyTheme(saved === "dark" ? "dark" : "light");
}

function fhBindThemeToggle(buttonId) {
  const btn = document.getElementById(buttonId);
  if (!btn) return;

  btn.addEventListener("click", () => {
    const current = document.documentElement.getAttribute("data-theme");
    const next = current === "dark" ? "light" : "dark";
    fhApplyTheme(next);
    localStorage.setItem(FH_THEME_KEY, next);
  });
}

/**
 * Mostra um toast Bootstrap já existente no DOM.
 * @param {string} toastId
 * @param {string} [message]
 */
function fhShowToast(toastId, message) {
  const toastEl = document.getElementById(toastId);
  if (!toastEl) return;

  if (message) {
    const body = toastEl.querySelector(".toast-body");
    if (body) body.textContent = message;
  }

  const toast = bootstrap.Toast.getOrCreateInstance(toastEl);
  toast.show();
}

/**
 * Aplica o padrão de validação visual do Bootstrap a um formulário.
 * @param {string} formId
 * @param {(form: HTMLFormElement) => void} onValid
 */
function fhBindValidation(formId, onValid) {
  const form = document.getElementById(formId);
  if (!form) return;

  form.addEventListener("submit", (event) => {
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
      form.classList.add("was-validated");
      return;
    }
    form.classList.add("was-validated");
    if (onValid) onValid(form);
  });
}

/* =========================================================
   Validações do Cadastro (RF02)
   ========================================================= */

/**
 * Valida um CPF pelo algoritmo do dígito verificador (módulo 11).
 * Aceita com ou sem máscara.
 * @param {string} cpf
 * @returns {boolean}
 */
function fhValidarCPF(cpf) {
  const digits = (cpf || "").replace(/\D/g, "");
  if (digits.length !== 11) return false;
  // Rejeita sequências repetidas (ex.: 111.111.111-11), que passam
  // no cálculo do dígito mas não são CPFs válidos.
  if (/^(\d)\1{10}$/.test(digits)) return false;

  const calcDigito = (base) => {
    let soma = 0;
    let peso = base.length + 1;
    for (const d of base) {
      soma += Number(d) * peso;
      peso--;
    }
    const resto = soma % 11;
    return resto < 2 ? 0 : 11 - resto;
  };

  const d1 = calcDigito(digits.slice(0, 9));
  const d2 = calcDigito(digits.slice(0, 9) + d1);
  return digits === digits.slice(0, 9) + String(d1) + String(d2);
}

/** Aplica máscara 000.000.000-00 enquanto o usuário digita. */
function fhMascaraCPF(valor) {
  return valor
    .replace(/\D/g, "")
    .slice(0, 11)
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d{1,2})$/, "$1-$2");
}

/** Aplica máscara XX-XXXXXXXXX (DDD + número, com o 9º dígito do celular) enquanto o usuário digita. */
function fhMascaraTelefone(valor) {
  const d = valor.replace(/\D/g, "").slice(0, 11);
  if (d.length <= 2) return d;
  return `${d.slice(0, 2)}-${d.slice(2)}`;
}

/**
 * Aplica uma função de máscara a um <input>, preservando a posição
 * do cursor em relação aos dígitos já digitados — sem isso, o cursor
 * pula pro final a cada tecla e edições no meio do texto ficam
 * impossíveis (ex.: corrigir um número de telefone digitado errado).
 * @param {HTMLInputElement} input
 * @param {(valor: string) => string} maskFn
 */
function fhAplicarMascaraComCursor(input, maskFn) {
  const posAntes = input.selectionStart ?? input.value.length;
  const digitosAntesDoCursor = input.value
    .slice(0, posAntes)
    .replace(/\D/g, "").length;

  const novoValor = maskFn(input.value);
  input.value = novoValor;

  if (digitosAntesDoCursor === 0) {
    input.setSelectionRange(0, 0);
    return;
  }

  let contados = 0;
  let novaPos = novoValor.length;
  for (let i = 0; i < novoValor.length; i++) {
    if (/\d/.test(novoValor[i])) {
      contados++;
      if (contados === digitosAntesDoCursor) {
        novaPos = i + 1;
        break;
      }
    }
  }
  input.setSelectionRange(novaPos, novaPos);
}

/**
 * Busca um CEP na API ViaCEP e preenche os campos de endereço.
 * @param {string} cep
 * @param {{logradouro: HTMLInputElement, bairro: HTMLInputElement, cidade: HTMLInputElement, uf: HTMLInputElement, status: HTMLElement}} campos
 */
async function fhBuscarCEP(cep, campos) {
  const digits = cep.replace(/\D/g, "");
  campos.status.classList.remove("is-error", "is-ok");

  if (digits.length !== 8) {
    campos.status.textContent = "";
    return;
  }

  campos.status.textContent = "Buscando endereço...";

  try {
    const resp = await fetch(`https://viacep.com.br/ws/${digits}/json/`);
    const data = await resp.json();

    if (data.erro) {
      campos.status.textContent =
        "CEP não encontrado — preencha o endereço manualmente.";
      campos.status.classList.add("is-error");
      return;
    }

    campos.logradouro.value = data.logradouro || "";
    campos.bairro.value = data.bairro || "";
    campos.cidade.value = data.localidade || "";
    campos.uf.value = data.uf || "";
    campos.status.textContent = "Endereço preenchido automaticamente.";
    campos.status.classList.add("is-ok");
  } catch (e) {
    campos.status.textContent =
      "Não foi possível consultar o CEP agora — preencha manualmente.";
    campos.status.classList.add("is-error");
  }
}

function fhBindCadastroForm() {
  const form = document.getElementById("cadastroForm");
  if (!form) return;

  const cpfInput = document.getElementById("cpf");
  const telefoneInput = document.getElementById("telefone");
  const cepInput = document.getElementById("cep");
  const senhaInput = document.getElementById("senha");
  const confirmarSenhaInput = document.getElementById("confirmarSenha");
  const perguntaSelects = form.querySelectorAll("select[name^='pergunta']");

  const camposEndereco = {
    logradouro: document.getElementById("logradouro"),
    bairro: document.getElementById("bairro"),
    cidade: document.getElementById("cidade"),
    uf: document.getElementById("uf"),
    status: document.getElementById("cepStatus"),
  };

  if (cpfInput) {
    cpfInput.addEventListener("input", () => {
      fhAplicarMascaraComCursor(cpfInput, fhMascaraCPF);
    });
  }

  if (telefoneInput) {
    const telefoneHidden = document.getElementById("telefoneCompleto");
    const fhAtualizarTelefoneCompleto = () => {
      if (!telefoneHidden) return;
      const digits = telefoneInput.value.replace(/\D/g, "");
      if (!digits) {
        telefoneHidden.value = "";
        return;
      }
      const ddd = digits.slice(0, 2);
      const numero = digits.slice(2);
      telefoneHidden.value = numero ? `(+55)${ddd}-${numero}` : `(+55)${ddd}`;
    };
    telefoneInput.addEventListener("input", () => {
      fhAplicarMascaraComCursor(telefoneInput, fhMascaraTelefone);
      fhAtualizarTelefoneCompleto();
    });
    fhAtualizarTelefoneCompleto();
  }

  if (cepInput) {
    cepInput.addEventListener("input", () => {
      cepInput.value = cepInput.value.replace(/\D/g, "").slice(0, 8);
    });
    cepInput.addEventListener("blur", () => {
      fhBuscarCEP(cepInput.value, camposEndereco);
    });
  }

  // Impede escolher a mesma pergunta de segurança duas vezes.
  function fhAtualizarPerguntasDisponiveis() {
    const selecionadas = Array.from(perguntaSelects)
      .map((s) => s.value)
      .filter(Boolean);

    perguntaSelects.forEach((select) => {
      Array.from(select.options).forEach((opt) => {
        if (!opt.value) return;
        opt.disabled =
          selecionadas.includes(opt.value) && select.value !== opt.value;
      });
    });
  }
  perguntaSelects.forEach((select) => {
    select.addEventListener("change", fhAtualizarPerguntasDisponiveis);
  });

  // Avisa em tempo real (não só ao enviar) se as senhas não coincidem.
  function fhVerificarSenhasIguais() {
    if (!senhaInput || !confirmarSenhaInput) return;

    if (confirmarSenhaInput.value === "") {
      confirmarSenhaInput.setCustomValidity("");
      confirmarSenhaInput.classList.remove("is-invalid", "is-valid");
      return;
    }

    if (senhaInput.value !== confirmarSenhaInput.value) {
      confirmarSenhaInput.setCustomValidity("As senhas não coincidem.");
      confirmarSenhaInput.classList.add("is-invalid");
      confirmarSenhaInput.classList.remove("is-valid");
    } else {
      confirmarSenhaInput.setCustomValidity("");
      confirmarSenhaInput.classList.remove("is-invalid");
      confirmarSenhaInput.classList.add("is-valid");
    }
  }
  if (senhaInput) senhaInput.addEventListener("input", fhVerificarSenhasIguais);
  if (confirmarSenhaInput) confirmarSenhaInput.addEventListener("input", fhVerificarSenhasIguais);

  form.addEventListener("submit", (event) => {
    let valido = form.checkValidity();

    if (cpfInput && !fhValidarCPF(cpfInput.value)) {
      cpfInput.setCustomValidity("CPF inválido.");
      valido = false;
    } else if (cpfInput) {
      cpfInput.setCustomValidity("");
    }

    fhVerificarSenhasIguais();
    if (confirmarSenhaInput && confirmarSenhaInput.classList.contains("is-invalid")) {
      valido = false;
    }

    if (!valido) {
      event.preventDefault();
      event.stopPropagation();
    }
    form.classList.add("was-validated");
  });
}

fhInitTheme();

document.addEventListener("DOMContentLoaded", () => {
  fhBindThemeToggle("themeToggle");
  fhBindValidation("loginForm");
  fhBindCadastroForm();
});
