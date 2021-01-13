<template>
  <div>
    <div class="bg-white shadow overflow-hidden rounded-lg">
      <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">Account</h3>
        <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">Personal information</p>
      </div>

      <div class="bg-red-50 p-4" v-if="$page.props.errors">
        <div class="text-sm leading-5 text-red-700">
          <ul class="pl-5 list-disc" v-for="(errorArray, index) in $page.props.errors" :key="index">
            <li v-for="(errorText,index) in errorArray" :key="index">{{ errorText }}</li>
          </ul>
        </div>
      </div>

      <div class="sm:col-span-2">
        <dd class="mt-1text-sm leading-5 text-gray-900">
          <div class="block">
            <div class="flex items-center px-3 sm:px-6 py-4">
              <div class="min-w-0 flex-1 flex items-center">
                <div class="flex-shrink-0">
                  <img class="h-16 w-16 rounded-full" :src="data.avatar_url" alt />
                </div>
                <div class="min-w-0 flex-1 px-4 md:grid md:grid-cols-2 md:gap-4">
                  <div>
                    <div
                      class="text-sm leading-5 font-medium text-gray-900 truncate"
                    >{{ data.username }}</div>
                    <div class="mt-2 flex items-center text-sm leading-5 text-gray-500">
                      <svg
                        class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                      >
                        <path
                          d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"
                        />
                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                      </svg>
                      <span>{{ email }}</span>
                    </div>
                  </div>
                  <div class="hidden md:flex">
                    <div class="text-sm leading-5 self-center text-gray-500">
                      Created on
                      <time :datetime="data.created_at">{{ onlyDate(data.created_at) }}</time>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </dd>
      </div>
      <div>
        <dl>
          <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
            <dt class="text-sm leading-5 font-medium self-center text-gray-500">Username</dt>

            <dd
              class="mt-1 text-sm flex items-center leading-5 text-gray-900 sm:mt-0 sm:col-span-2"
              v-if="edit.username"
            >
              <input
                @keyup.enter="save"
                v-model="username"
                class="flex-1 bg-white border-gray-200 rounded block border py-1 px-3 sm:text-sm focus:border-cool-gray-300"
              />
              <span @click="save" class="ml-2 text-orange-500 cursor-pointer hover:underline">Save</span>
              <span
                @click="cancel('username')"
                class="ml-2 text-orange-500 cursor-pointer hover:underline"
              >Cancel</span>
            </dd>
            <dd
              class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2"
              v-if="!edit.username"
            >
              {{username }}
              <span
                @click="editSection('username')"
                class="ml-2 text-orange-500 cursor-pointer hover:underline"
              >Edit</span>
            </dd>
          </div>
          <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
            <dt class="text-sm leading-5 font-medium self-center text-gray-500">Email</dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
              {{email }}
              <!-- <span class="ml-2 text-orange-500 cursor-pointer hover:underline">Edit</span> -->
            </dd>
          </div>
          <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
            <dt class="text-sm leading-5 font-medium self-start text-gray-500">Password</dt>

            <div v-if="edit.password">
              <dd class="mt-1 text-sm flex flex-col leading-5 text-gray-900 sm:mt-0 col-span-1">
                <input
                  placeholder="Old password"
                  name="old_password"
                  aria-label="Current password"
                  v-model="oldPassword"
                  type="password"
                  class="flex-1 bg-white border-gray-200 rounded block border py-1 px-3 sm:text-sm focus:border-cool-gray-300"
                />

                <input
                  placeholder="New password"
                  name="new_password"
                  v-model="newPassword"
                  aria-label="New password"
                  @keyup.enter="changePassword"
                  type="password"
                  class="flex-1 bg-white border-gray-200 rounded block border mt-3 py-1 px-3 sm:text-sm focus:border-cool-gray-300"
                />
              </dd>
              <div class="flex flex-row col-span-2">
                <span
                  @click="changePassword"
                  class="text-white px-3 py-1 rounded w-full text-sm text-center bg-theme-primary cursor-pointer mt-3 hover:underline"
                >Change password</span>
              </div>
            </div>
            <dd
              v-else
              @click="editSection('password')"
              class="mt-1 text-sm leading-5 cursor-pointer text-orange-500 sm:mt-0 sm:col-span-2"
            >Change password</dd>
          </div>

          <!-- <div class="sm:col-span-2 py-5 px-6">
            <dt class="text-sm leading-5 mb-5 font-medium text-gray-500">Attachments</dt>
            <dd class="mt-1text-sm leading-5 text-gray-900">
              <div class="w-40">
                <button-danger text="Delete my account"></button-danger>
              </div>
            </dd>
          </div>-->
        </dl>
      </div>
    </div>
  </div>
</template>

<script>
import moment from "moment";

export default {
  props: ["data"],
  data() {
    return {
      edit: { username: false, password: false },
      username: this.data.username,
      email: this.data.email,
      oldPassword: "",
      newPassword: "",
      showPasswordModal: false,
    };
  },
  methods: {
    editSection(section) {
      this.edit[section] = true;
    },
    cancel(section) {
      this.username = this.data.username;
      this.edit[section] = false;
    },
    changePassword() {
      let userId = this.data.id;
      return this.$inertia.put(
        this.$route("user.password.update", { user: userId }),
        {
          old_password: this.oldPassword,
          new_password: this.newPassword,
        },
        {
          replace: false,
          preserveState: true,
          preserveScroll: false,
          only: [],
        }
      );
    },
    save() {
      let userId = this.data.id;

      this.edit["username"] = false;
      return this.$inertia.put(
        this.$route("user.update", { user: userId }),
        { username: this.username },
        {
          replace: false,
          preserveState: true,
          preserveScroll: false,
          only: [],
        }
      );
    },
    onlyDate(datetime) {
      return moment.utc(datetime).format("LL");
    },
  },
};
</script>

<style scoped>
</style>
