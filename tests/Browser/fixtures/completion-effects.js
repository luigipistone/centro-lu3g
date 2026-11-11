import { createApp, h, ref } from "vue";
import Effect from "../../../resources/js/Components/PlayfulCompletionEffect.vue";
createApp({
    setup() {
        const effect = ref(null);
        const sequence = ref(0);
        return () =>
            h("main", [
                ...["snowfall", "mario", "rocket"].map((value) =>
                    h(
                        "button",
                        {
                            onClick: () => {
                                effect.value = value;
                                sequence.value++;
                            },
                        },
                        value,
                    ),
                ),
                effect.value
                    ? h(Effect, {
                          effect: effect.value,
                          key: sequence.value,
                          onFinished: () => {
                              effect.value = null;
                          },
                      })
                    : null,
            ]);
    },
}).mount("#app");
