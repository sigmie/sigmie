<template>
  <form @submit.prevent="submit">
    <div class="p-6">
      <div class="border-gray-200">
        <csrf />
        <div>
          <h3 class="text-lg leading-6 font-medium text-gray-900">Sign up With Github</h3>
        </div>

        <button-github :route="githubRoute" class="mt-3" />

        <div class="mt-4 relative">
          <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-gray-300"></div>
          </div>
          <div class="relative flex justify-center text-sm leading-5">
            <span class="px-2 bg-white text-gray-500">Or</span>
          </div>
        </div>

        <div class="sm:col-span-3 pb-2">
          <form-input
            :value="email"
            @change="(value) => set('email',value)"
            class="pt-4"
            id="email"
            name="email"
            type="text"
            label="Email address"
            :validations="$v.email"
            :error-messages="errorMessages.email"
          />
        </div>
        <div class="sm:col-span-3 pb-2">
          <form-input
            :value="password"
            @change="(value) => set('password',value)"
            class="pt-4"
            id="password"
            name="password"
            type="password"
            label="Password"
            :validations="$v.password"
            :error-messages="errorMessages.password"
          />
        </div>
        <div class="sm:col-span-3 pb-2">
          <form-input
            :value="password_confirmation"
            @change="(value) => set('password_confirmation',value)"
            class="pt-4"
            id="password_cofirmation"
            name="password_confirmation"
            type="password"
            label="Password confirm"
            :validations="$v.password_confirmation"
            :error-messages="errorMessages.password_confirmation"
          />
        </div>
      </div>
    </div>
    <div class="mt-2 border-t border-gray-200 p-6">
      <div>
        <h3 class="text-lg leading-6 font-medium text-gray-900">Billing</h3>
        <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">Billing information</p>
      </div>

      <div class="mt-4 bg-white">
        <form-select
          label="Plan"
          name="plan"
          id="plan"
          v-model.trim="$v.plan.$model"
          aria-label="Billin plan"
          :items="['Hobby','Pro','Serious']"
          :validations="$v.plan"
        />
      </div>
      <div class="pt-2">
        <div class="sm:col-span-3 pb-2">
          <form-input
            :value="name"
            @change="(value) => set('name',value)"
            class="pt-4"
            label="Name"
            id="name"
            name="name"
            :validations="$v.name"
            :error-messages="errorMessages.name"
          />
        </div>
      </div>
      <div class="pt-2">
        <div class="sm:col-span-3 pb-2">
          <stripe v-model.trim="$v.method.$model" ref="stripe" :name="name" class="pt-4" />
        </div>
      </div>
    </div>
    <div class="border-gray-200 p-5">
      <div class="flex justify-end">
        <span class="ml-3 inline-flex rounded-md shadow-sm">
          <button-primary :class="{ 'disabled': $v.$anyError }" text="Register" type="submit" />
        </span>
      </div>
    </div>
  </form>
</template>

<script>
import {
  required,
  minLength,
  between,
  email,
  sameAs,
  helpers
} from "vuelidate/lib/validators";

export default {
  props: ["githubRoute", "user"],
  data() {
    return {
      name: app.old.name ? app.old.name : "",
      email: app.old.email ? app.old.email : "",
      password: "",
      password_confirmation: "",
      plan: app.old.plan ? app.old.plan : "Hobby",
      method: "",
      errorMessages: {
        email: {
          required: "Email address is required.",
          email: "Invalid email address format."
        },
        password: {
          minLength: "Password must be at least 8 chars.",
          required: "Password can't be empty."
        },
        password_confirmation: {
          sameAsPassword: "Passwords don't match."
        },
        name: {
          required: "Name field is required."
        }
      }
    };
  },
  validations: {
    password: {
      required,
      minLength: minLength(8)
    },
    password_confirmation: {
      sameAsPassword: sameAs("password")
    },
    name: {
      required
    },
    plan: {
      required
    },
    email: {
      required,
      email
    },
    method: {
      required
    }
  },
  methods: {
    async submit(event) {
      await this.$refs.stripe.fetchMethod();

      if (this.$v.$anyError === false) {
        event.target.submit();
      }
    },
    set(key, value) {
      this[key] = value;
      this.$v[key].$touch();
    }
  }
};
</script>

<style>
</style>
